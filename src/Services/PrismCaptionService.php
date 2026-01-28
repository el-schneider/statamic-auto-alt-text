<?php

declare(strict_types=1);

namespace ElSchneider\StatamicAutoAltText\Services;

use ElSchneider\StatamicAutoAltText\Contracts\CaptionService;
use ElSchneider\StatamicAutoAltText\Events\AfterCaptionGeneration;
use ElSchneider\StatamicAutoAltText\Events\BeforeCaptionGeneration;
use ElSchneider\StatamicAutoAltText\Exceptions\CaptionGenerationException;
use ElSchneider\StatamicAutoAltText\Services\Concerns\ParsesPrompts;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Prism\Prism\Exceptions\PrismException;
use Prism\Prism\Facades\Prism;
use Prism\Prism\ValueObjects\Media\Image;
use Statamic\Assets\Asset;
use Throwable;

final class PrismCaptionService implements CaptionService
{
    use ParsesPrompts;

    private const TARGET_FORMAT = 'webp';

    public function __construct(
        private readonly ImageProcessor $imageProcessor,
        private readonly array $config,
    ) {}

    public function generateCaption(Asset $asset): ?string
    {
        if (! $this->supportsAssetType($asset)) {
            Log::warning("PrismCaptionService: Unsupported asset type: {$asset->mimeType()} ({$asset->id()})");

            return null;
        }

        Event::dispatch(new BeforeCaptionGeneration($asset));

        try {
            $caption = $this->generateWithPrism($asset);

            if ($caption === null) {
                return null;
            }

            if ($caption === '') {
                throw new CaptionGenerationException('AI returned an empty caption.');
            }

            Event::dispatch(new AfterCaptionGeneration($asset, $caption));

            return $caption;

        } catch (PrismException $e) {
            Log::error('PrismCaptionService: API error', [
                'error' => $e->getMessage(),
                'asset_id' => $asset->id(),
            ]);

            throw new CaptionGenerationException("API error: {$e->getMessage()}", 0, $e);
        } catch (Throwable $e) {
            Log::error('PrismCaptionService: Generation failed', [
                'error' => $e->getMessage(),
                'asset_id' => $asset->id(),
            ]);

            throw new CaptionGenerationException("Failed to generate caption: {$e->getMessage()}", 0, $e);
        }
    }

    public function supportsAssetType(Asset $asset): bool
    {
        return $asset->isImage();
    }

    /**
     * Generate caption using Prism API.
     *
     * @throws CaptionGenerationException
     * @throws PrismException
     */
    private function generateWithPrism(Asset $asset): ?string
    {
        $base64DataUrl = $this->imageProcessor->processImageToBase64($asset, self::TARGET_FORMAT);

        if (! $base64DataUrl) {
            Log::warning("PrismCaptionService: Could not process image: {$asset->path()}");

            return null;
        }

        [$provider, $model] = $this->parseModel($this->config['model']);
        $parsedPrompt = $this->parsePrompt($this->config['prompt'], $asset);

        if (config('statamic.auto-alt-text.log_completions')) {
            Log::debug('PrismCaptionService: Sending request', [
                'asset_id' => $asset->id(),
                'provider' => $provider,
                'model' => $model,
                'system_message' => $this->config['system_message'],
                'prompt' => $parsedPrompt,
                'max_tokens' => $this->config['max_tokens'],
                'temperature' => $this->config['temperature'],
                'timeout' => $this->config['timeout'],
            ]);
        }

        $response = Prism::text()
            ->using($provider, $model)
            ->withSystemPrompt($this->config['system_message'])
            ->withPrompt(
                $parsedPrompt,
                [Image::fromBase64($this->extractBase64Content($base64DataUrl))]
            )
            ->withMaxTokens($this->config['max_tokens'])
            ->usingTemperature($this->config['temperature'])
            ->withClientOptions(['timeout' => $this->config['timeout']])
            ->asText();

        if (config('statamic.auto-alt-text.log_completions')) {
            Log::debug('PrismCaptionService: Received response', [
                'asset_id' => $asset->id(),
                'finish_reason' => $response->finishReason->name,
                'caption' => $response->text,
                'caption_length' => mb_strlen($response->text),
                'prompt_tokens' => $response->usage->promptTokens,
                'completion_tokens' => $response->usage->completionTokens,
                'response_id' => $response->meta->id,
                'response_model' => $response->meta->model,
            ]);
        }

        return $response->text;
    }

    /**
     * Extract base64 content from a data URL.
     */
    private function extractBase64Content(string $dataUrl): string
    {
        return preg_match('/^data:[^;]+;base64,(.+)$/', $dataUrl, $matches)
            ? $matches[1]
            : $dataUrl;
    }

    /**
     * Parse "provider/model" format into provider and model strings.
     *
     * @return array{0: string, 1: string}
     */
    private function parseModel(string $model): array
    {
        if (! str_contains($model, '/')) {
            throw new CaptionGenerationException(
                "Invalid model format: '{$model}'. Expected 'provider/model' (e.g., 'openai/gpt-4.1')."
            );
        }

        return explode('/', $model, 2);
    }
}
