<?php

declare(strict_types=1);

namespace ElSchneider\StatamicAutoAltText\Services;

use ElSchneider\StatamicAutoAltText\Contracts\CaptionService;
use ElSchneider\StatamicAutoAltText\Events\AfterCaptionGeneration;
use ElSchneider\StatamicAutoAltText\Events\BeforeCaptionGeneration;
use ElSchneider\StatamicAutoAltText\Exceptions\CaptionGenerationException;
use Illuminate\Http\Client\Factory as HttpClient;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Statamic\Assets\Asset;
use Throwable;

final class OpenAIService implements CaptionService
{
    private HttpClient $http;

    private string $apiKey;

    private string $model;

    private string $endpoint;

    private string $prompt;

    private int $maxTokens;

    private string $altTextField;

    public function __construct(HttpClient $http, array $config)
    {
        $this->http = $http;
        $this->apiKey = $config['api_key'] ?? '';
        $this->model = $config['model'] ?? 'gpt-4-turbo';
        $this->endpoint = $config['endpoint'] ?? 'https://api.openai.com/v1/chat/completions';
        $this->prompt = $config['prompt'] ?? 'Describe this image concisely for accessibility alt text.';
        $this->maxTokens = $config['max_tokens'] ?? 100;
        $this->altTextField = config('statamic.auto-alt-text.alt_text_field', 'alt');

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
            $imageUrl = $asset->absoluteUrl();

            $response = $this->http->withToken($this->apiKey)
                ->timeout(60)
                ->post($this->endpoint, [
                    'model' => $this->model,
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => [
                                [
                                    'type' => 'text',
                                    'text' => $this->prompt,
                                ],
                                [
                                    'type' => 'image_url',
                                    'image_url' => [
                                        'url' => $imageUrl,
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'max_tokens' => $this->maxTokens,
                ]);

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

            // Clean up the caption (remove quotes, trim whitespace)
            $caption = mb_trim($caption, " \\n\\r\\t\\v\\x00\"'");

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
        return $asset->extensionIsOneOf(['png', 'jpeg', 'gif', 'webp']);
    }
}
