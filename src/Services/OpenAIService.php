<?php

declare(strict_types=1);

namespace ElSchneider\StatamicAutoAltText\Services;

use ElSchneider\StatamicAutoAltText\Contracts\CaptionService;
use ElSchneider\StatamicAutoAltText\Events\AfterCaptionGeneration;
use ElSchneider\StatamicAutoAltText\Events\BeforeCaptionGeneration;
use ElSchneider\StatamicAutoAltText\Exceptions\CaptionGenerationException;
use ElSchneider\StatamicAutoAltText\Services\Concerns\ParsesPrompts;
use Illuminate\Http\Client\Factory as HttpClient;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Statamic\Assets\Asset;
use Throwable;

final class OpenAIService implements CaptionService
{
    use ParsesPrompts;

    private const TARGET_FORMAT = 'webp';

    private HttpClient $http;

    private ImageProcessor $imageProcessor;

    private string $apiKey;

    private string $model;

    private string $endpoint;

    private string $systemMessage;

    private string $prompt;

    private int $maxTokens;

    private string $detail;

    private array $params;

    public function __construct(HttpClient $http, ImageProcessor $imageProcessor, array $config)
    {
        $this->http = $http;
        $this->imageProcessor = $imageProcessor;
        $this->apiKey = $config['api_key'] ?? '';
        $this->model = $config['model'] ?? 'gpt-4-turbo';
        $this->endpoint = $config['endpoint'] ?? 'https://api.openai.com/v1/chat/completions';
        $this->systemMessage = $config['system_message'] ?? 'You are an accessibility expert generating concise, descriptive alt text for images. Focus on the most important visual elements that convey meaning and context. Keep descriptions brief but informative for screen readers.';
        $this->prompt = $config['prompt'] ?? 'Describe this image for accessibility alt text.';
        $this->maxTokens = $config['max_tokens'] ?? 100;
        $this->detail = $config['detail'] ?? 'auto';
        $this->params = $config['params'] ?? [];

        if (empty($this->apiKey)) {
            Log::error('OpenAI API key is not configured for Statamic Auto Alt Text.');
        }
    }

    public function generateCaption(Asset $asset): ?string
    {
        if (! $this->supportsAssetType($asset)) {
            Log::warning("OpenAI Service: Unsupported asset type for caption generation: {$asset->mimeType()} ({$asset->id()})");

            return null;
        }

        Event::dispatch(new BeforeCaptionGeneration($asset));

        try {
            $base64Image = $this->imageProcessor->processImageToBase64($asset, self::TARGET_FORMAT);

            if (! $base64Image) {
                Log::warning("OpenAI Service: Could not process image for Base64 encoding: {$asset->path()}");

                return null;
            }

            // Parse prompt with Antlers templating
            $parsedPrompt = $this->parsePrompt($this->prompt, $asset);

            Log::info("Parsed prompt: {$parsedPrompt}");

            $payload = $this->buildPayload($parsedPrompt, $base64Image);

            $response = $this->http->withToken($this->apiKey)
                ->timeout(60)
                ->post($this->endpoint, $payload);

            if ($response->failed()) {
                Log::error('OpenAI API request failed.', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'asset_id' => $asset->id(),
                ]);
                throw new CaptionGenerationException("OpenAI API error: {$response->status()} - {$response->reason()}");
            }

            $caption = $response->json('choices.0.message.content');

            if (empty($caption)) {
                Log::warning('OpenAI API returned an empty caption.', [
                    'response_body' => $response->json(),
                    'asset_id' => $asset->id(),
                ]);
                throw new CaptionGenerationException('OpenAI API returned an empty caption.');
            }

            if (config('statamic.auto-alt-text.log_completions', false)) {
                Log::info("OpenAI Service: Generated caption for asset {$asset->id()}: {$caption}");
            }

            Event::dispatch(new AfterCaptionGeneration($asset, $caption));

            return $caption;

        } catch (Throwable $e) {
            Log::error('Error generating caption with OpenAI.', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'asset_id' => $asset->id(),
            ]);

            throw new CaptionGenerationException("Failed to generate caption for asset {$asset->id()}: {$e->getMessage()}", 0, $e);
        }
    }

    public function supportsAssetType(Asset $asset): bool
    {
        // Support any image that can be processed (excludes SVGs and non-images)
        return $asset->isImage();
    }

    private function buildPayload(string $prompt, string $base64Image): array
    {
        $payload = [
            'messages' => [
                ['role' => 'system', 'content' => $this->systemMessage],
                [
                    'role' => 'user',
                    'content' => [
                        ['type' => 'text', 'text' => $prompt],
                        [
                            'type' => 'image_url',
                            'image_url' => [
                                'url' => $base64Image,
                                'detail' => $this->params['detail'] ?? $this->detail,
                            ],
                        ],
                    ],
                ],
            ],
        ];

        // Add model (params override legacy)
        if (! isset($this->params['model'])) {
            $payload['model'] = $this->model;
        }

        // Add max_tokens only if not using max_completion_tokens
        if (! isset($this->params['max_tokens']) && ! isset($this->params['max_completion_tokens'])) {
            $payload['max_tokens'] = $this->maxTokens;
        }

        // Merge any params (overrides everything)
        return array_merge($payload, $this->params);
    }
}
