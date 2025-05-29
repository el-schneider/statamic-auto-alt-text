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
use Intervention\Image\ImageManagerStatic as InterventionImage;
use Statamic\Assets\Asset;
use Statamic\Assets\AssetContainer;

final class MoondreamService implements CaptionService
{
    private HttpClient $httpClient;

    private string $endpoint;

    private ?string $apiKey;

    private array $options;

    private ?int $maxDimensionPixels;

    public function __construct(HttpClient $httpClient, array $config)
    {
        $this->httpClient = $httpClient;
        $this->endpoint = $config['endpoint'] ?? '';
        $this->apiKey = $config['api_key'] ?? null;
        $this->options = $config['options'] ?? [];
        $this->maxDimensionPixels = config('statamic.auto-alt-text.max_dimension_pixels');

        if (empty($this->endpoint)) {
            Log::error('Moondream endpoint is not configured.');
        }

        // Check if using cloud API without API key
        if (str_contains($this->endpoint, 'api.moondream.ai') && empty($this->apiKey)) {
            Log::error('Moondream cloud API key is required when using the cloud endpoint (api.moondream.ai). Please set MOONDREAM_API_KEY in your .env file or use a local endpoint.');
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
        $assetData = $this->getAssetData($asset);
        if (! $assetData) {
            return null;
        }

        [$originalContent, $originalMimeType, $diskName, $path] = $assetData;

        try {
            $width = $asset->width();
            $height = $asset->height();

            if ($width === null || $height === null) {
                Log::warning("Could not determine dimensions for asset [Disk: {$diskName}, Path: {$path}]. Skipping resize check.");
                $finalContent = $originalContent;
                $finalMimeType = $originalMimeType;
            } else {
                $needsResizing = $this->maxDimensionPixels !== null &&
                                 ($width > $this->maxDimensionPixels || $height > $this->maxDimensionPixels);

                if ($needsResizing) {
                    Log::debug("Attempting to resize image in memory [Disk: {$diskName}, Path: {$path}]");
                    [$finalContent, $finalMimeType] = $this->resizeImageContent(
                        $originalContent,
                        $originalMimeType,
                        $width,
                        $height
                    );
                } else {
                    $finalContent = $originalContent;
                    $finalMimeType = $originalMimeType;
                }
            }

        } catch (Exception $e) {
            Log::error("Error processing image dimensions or resizing [Disk: {$diskName}, Path: {$path}]: {$e->getMessage()}");
            Log::error($e->getTraceAsString());

            return null;
        }

        if (empty($finalContent) || empty($finalMimeType)) {
            Log::error("Final content or mime type empty after processing [Disk: {$diskName}, Path: {$path}]");

            return null;
        }

        return 'data:'.$finalMimeType.';base64,'.base64_encode($finalContent);
    }

    /**
     * Get the content, mime type, disk name, and path for an asset.
     * Returns null if the asset cannot be read.
     */
    private function getAssetData(Asset $asset): ?array
    {
        try {
            /** @var AssetContainer $container */
            $container = $asset->container();
            $diskName = $container->handle();
            $path = $asset->path();
            $storage = Storage::disk($diskName);

            if (! $storage->exists($path)) {
                Log::error("Asset file not found on disk '{$diskName}' at path: {$path}");

                return null;
            }

            $content = $storage->get($path);
            $mimeType = $storage->mimeType($path); // Linter might flag this, but it's correct Laravel Storage usage

            if (empty($content) || empty($mimeType)) {
                Log::error("Failed to read content or mime type [Disk: {$diskName}, Path: {$path}]");

                return null;
            }

            return [$content, $mimeType, $diskName, $path];

        } catch (Exception $e) {
            $logDisk = isset($diskName) ? $diskName : 'unknown';
            $logPath = isset($path) ? $path : $asset->path() ?? 'unknown';
            Log::error("Error accessing asset file [Source: {$logDisk}, Path: {$logPath}]: {$e->getMessage()}");
            Log::error($e->getTraceAsString());

            return null;
        }
    }

    /**
     * Resize image content if necessary based on configured max dimensions.
     * Returns an array containing the (potentially resized) content and final mime type.
     */
    private function resizeImageContent(string $content, string $mimeType, int $width, int $height): array
    {
        $image = InterventionImage::make($content);

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

        $format = Str::after($mimeType, '/');
        $supportedFormats = ['jpg', 'jpeg', 'png', 'gif', 'tif', 'bmp', 'webp'];

        if (! in_array($format, $supportedFormats)) {
            Log::warning("Unsupported image format for Intervention encode '{$format}', falling back to jpg.");
            $format = 'jpg';
            $finalMimeType = 'image/jpeg';
        } else {
            $finalMimeType = $mimeType;
        }

        $quality = ($format === 'jpeg' || $format === 'jpg') ? 85 : null;
        $resizedContent = (string) $image->encode($format, $quality);

        return [$resizedContent, $finalMimeType];
    }

    private function makeApiRequest(string $base64Image): array
    {
        $client = $this->httpClient->timeout(config('statamic.auto-alt-text.api_timeout', 30));
        $headers = ['Content-Type' => 'application/json'];

        if ($this->apiKey) {
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
