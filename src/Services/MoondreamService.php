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
use Statamic\Assets\Asset;

final class MoondreamService implements CaptionService
{
    private const TARGET_FORMAT = 'jpeg';

    private HttpClient $httpClient;

    private ImageProcessor $imageProcessor;

    private string $endpoint;

    private ?string $apiKey;

    private array $options;

    public function __construct(HttpClient $httpClient, ImageProcessor $imageProcessor, array $config)
    {
        $this->httpClient = $httpClient;
        $this->imageProcessor = $imageProcessor;
        $this->endpoint = $config['endpoint'] ?? '';
        $this->apiKey = $config['api_key'] ?? null;
        $this->options = $config['options'] ?? [];

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
            $base64Image = $this->imageProcessor->processImageToBase64($asset, self::TARGET_FORMAT);
            if (! $base64Image) {
                Log::warning("Could not process image file for Base64 encoding: {$asset->path()}");

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
        // Support any image that can be processed (excludes SVGs and non-images)
        return $asset->isImage();
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
