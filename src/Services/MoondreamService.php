<?php

declare(strict_types=1);

namespace ElSchneider\StatamicAutoAltText\Services;

use ElSchneider\StatamicAutoAltText\Contracts\CaptionService;
use ElSchneider\StatamicAutoAltText\Events\AfterCaptionGeneration;
use ElSchneider\StatamicAutoAltText\Events\BeforeCaptionGeneration;
use ElSchneider\StatamicAutoAltText\Exceptions\CaptionGenerationException;
use Exception;
use Illuminate\Http\Client\Factory as HttpClient;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Image;
use Statamic\Assets\Asset;

final class MoondreamService implements CaptionService
{
    private string $mode;

    private string $endpoint;

    private ?string $apiKey;

    private HttpClient $httpClient;

    private ?int $maxDimensionPixels;

    public function __construct(HttpClient $httpClient)
    {
        $this->httpClient = $httpClient;
        $this->mode = config('statamic.auto-alt-text.services.moondream.mode', 'cloud');
        $this->maxDimensionPixels = config('statamic.auto-alt-text.max_dimension_pixels');

        if ($this->mode === 'cloud') {
            $this->endpoint = config('statamic.auto-alt-text.services.moondream.cloud.endpoint');
            $this->apiKey = config('statamic.auto-alt-text.services.moondream.cloud.api_key');
        } else {
            $this->endpoint = config('statamic.auto-alt-text.services.moondream.local.endpoint');
            $this->apiKey = null;
        }
    }

    public function generateCaption(Asset $asset): ?string
    {
        if (! $this->supportsAssetType($asset)) {
            Log::warning("Asset type not supported for caption generation: {$asset->path()}");

            return null;
        }

        Event::dispatch(new BeforeCaptionGeneration($asset));

        try {
            $base64Image = $this->readImageToBase64($asset);
            if (! $base64Image) {
                Log::warning("Could not read image file for Base64 encoding: {$asset->path()}");

                return null;
            }

            $response = $this->makeApiRequest($base64Image);
            $caption = $response['caption'] ?? null;

            if ($caption && config('statamic.auto-alt-text.log_completions', true)) {
                Log::info("Generated caption for {$asset->path()}: {$caption}");
            }

            Event::dispatch(new AfterCaptionGeneration($asset, $caption));

            return $caption;
        } catch (Exception $e) {
            if ($e instanceof CaptionGenerationException) {
                Log::error($e->getMessage());
            } else {
                Log::error("Unexpected error generating caption for {$asset->path()}: {$e->getMessage()}");
                Log::error($e->getTraceAsString());
            }

            if ($e instanceof CaptionGenerationException) {
                throw $e;
            }

            return null;
        }
    }

    public function generateCaptions(array $assets): array
    {
        $results = [];

        foreach ($assets as $asset) {
            try {
                $results[$asset->id()] = $this->generateCaption($asset);
            } catch (Exception $e) {
                $results[$asset->id()] = null;

                if (config('statamic.auto-alt-text.log_completions', true)) {
                    Log::error("Error in batch caption generation for {$asset->path()}: {$e->getMessage()}");
                }
                // Continue processing other assets in the batch
            }
        }

        return $results;
    }

    /**
     * Check if the service supports generating a caption for the given asset type.
     * Currently excludes SVGs due to processing limitations.
     */
    public function supportsAssetType(Asset $asset): bool
    {
        $mimeType = $asset->mimeType() ?? '';

        // SVGs are currently excluded as they may not be easily processed by GD
        if ($mimeType === 'image/svg+xml') {
            return false;
        }

        // Check if the mime type starts with 'image/' for other types
        return Str::startsWith($mimeType, 'image/');
    }

    /**
     * Reads image content, potentially resizes it, and returns Base64 encoded string.
     */
    private function readImageToBase64(Asset $asset): ?string
    {
        $diskName = null;
        $path = null;
        $finalContent = null;
        $finalMimeType = null;

        try {
            $diskName = $asset->container()->diskHandle();
            $path = $asset->path();
            $storage = Storage::disk($diskName);

            if (! $storage->exists($path)) {
                Log::error("Asset file not found on disk '{$diskName}' at path: {$path}");

                return null;
            }

            $originalContent = $storage->get($path);
            $originalMimeType = $storage->mimeType($path);

            if (empty($originalContent) || empty($originalMimeType)) {
                Log::error("Failed to read original content or mime type [Disk: {$diskName}, Path: {$path}]");

                return null;
            }

            $width = $asset->width();
            $height = $asset->height();
            $needsResizing = $this->maxDimensionPixels !== null &&
                             ($width > $this->maxDimensionPixels || $height > $this->maxDimensionPixels);

            if ($needsResizing) {
                Log::debug("Resizing image in memory for captioning: {$path}");
                $image = Image::make($originalContent);

                $targetWidth = null;
                $targetHeight = null;
                if ($width > $height) {
                    $targetWidth = $this->maxDimensionPixels;
                } else {
                    $targetHeight = $this->maxDimensionPixels;
                }

                $image->resize($targetWidth, $targetHeight, function ($constraint) {
                    $constraint->aspectRatio();
                    $constraint->upsize();
                });

                $format = Str::after($originalMimeType, '/');
                $quality = ($format === 'jpeg' || $format === 'jpg') ? 85 : null;
                $finalContent = (string) $image->encode($format, $quality);
                $finalMimeType = $originalMimeType;

            } else {
                $finalContent = $originalContent;
                $finalMimeType = $originalMimeType;
            }

        } catch (Exception $e) {
            $logDisk = $diskName ?? 'unknown';
            $logPath = $path ?? 'unknown';
            Log::error("Error reading/resizing asset file [Source: {$logDisk}, Path: {$logPath}]: {$e->getMessage()}");
            Log::error($e->getTraceAsString());

            return null;
        }

        if (empty($finalContent) || empty($finalMimeType)) {
            Log::error("Final content or mime type empty after processing [Disk: {$diskName}, Path: {$path}]");

            return null;
        }

        return 'data:'.$finalMimeType.';base64,'.base64_encode($finalContent);
    }

    private function makeApiRequest(string $base64Image): array
    {
        $client = $this->httpClient->timeout(config('statamic.auto-alt-text.api_timeout', 30));
        $headers = ['Content-Type' => 'application/json'];

        if ($this->mode === 'cloud' && $this->apiKey) {
            $headers['X-Moondream-Auth'] = $this->apiKey;
        }

        $response = $client->withHeaders($headers)
            ->post($this->endpoint, [
                'image_url' => $base64Image,
            ]);

        if ($response->failed()) {
            throw new CaptionGenerationException(
                "Captioning API request failed with status {$response->status()}: {$response->body()}"
            );
        }

        return $response->json();
    }
}
