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
use Statamic\Assets\Asset;
use Statamic\Facades\Image;

final class MoondreamService implements CaptionService
{
    private HttpClient $httpClient;

    private string $mode;

    private string $endpoint;

    private ?string $apiKey;

    private array $options;

    private ?int $maxDimensionPixels;

    public function __construct(HttpClient $httpClient, array $config)
    {
        $this->httpClient = $httpClient;
        $this->mode = $config['mode'] ?? 'cloud';
        $this->maxDimensionPixels = config('statamic.auto-alt-text.max_dimension_pixels'); // Keep this global config for now

        if ($this->mode === 'cloud') {
            $this->endpoint = $config['cloud']['endpoint'] ?? '';
            $this->apiKey = $config['cloud']['api_key'] ?? null;
            $this->options = $config['cloud']['options'] ?? [];
        } else { // local mode
            $this->endpoint = $config['local']['endpoint'] ?? '';
            $this->apiKey = null; // No API key for local mode
            $this->options = $config['local']['options'] ?? [];
        }

        if (empty($this->endpoint)) {
            Log::error("Moondream endpoint is not configured for '{$this->mode}' mode.");
            // Potentially throw an exception
        }
        if ($this->mode === 'cloud' && empty($this->apiKey)) {
            Log::error('Moondream API key is not configured for cloud mode.');
            // Potentially throw an exception
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

    /**
     * Check if the service supports generating a caption for the given asset type.
     */
    public function supportsAssetType(Asset $asset): bool
    {
        // isImage excludes SVGs
        return $asset->isImage();
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

        $payload = [
            'image_url' => $base64Image,
        ] + $this->options;

        $response = $client->withHeaders($headers)->post($this->endpoint, $payload);

        if ($response->failed()) {
            throw new CaptionGenerationException(
                "Captioning API request failed with status {$response->status()}: {$response->body()}"
            );
        }

        return $response->json();
    }
}
