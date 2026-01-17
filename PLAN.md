# Migration Plan: Replace API Layer with Prism PHP

## Overview

Replace the homegrown OpenAI/Moondream API layer with [prism-php/prism](https://github.com/prism-php/prism), a Laravel package for LLM integration. This simplifies the codebase and gives users access to multiple AI providers.

## Breaking Changes

1. **Moondream support dropped** - Prism doesn't support Moondream
2. **Config structure changed** - `services.openai.*` replaced with flat `model` setting
3. **Environment variables changed:**
   - `AUTO_ALT_TEXT_MODEL` replaces `AUTO_ALT_TEXT_SERVICE`
   - `OPENAI_*` keys now managed by Prism's config
   - Remove: `MOONDREAM_*`, `OPENAI_ENDPOINT`, `OPENAI_DETAIL`

## New Configuration

### config/auto-alt-text.php

```php
<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | AI Model
    |--------------------------------------------------------------------------
    |
    | The AI model to use for generating alt text, in "provider/model" format.
    |
    | Supported providers: openai, anthropic, ollama, mistral, groq, deepseek, xai
    |
    | Examples:
    | - openai/gpt-4.1 (default)
    | - anthropic/claude-sonnet-4-5
    | - ollama/llava
    | - mistral/pixtral-large-latest
    | - groq/llava-v1.5-7b-4096-preview
    |
    | Configure API keys in config/prism.php (published via prism-php/prism).
    |
    */
    'model' => env('AUTO_ALT_TEXT_MODEL', 'openai/gpt-4.1'),

    /*
    |--------------------------------------------------------------------------
    | System Message
    |--------------------------------------------------------------------------
    |
    | Instructions for the AI on how to generate alt text.
    |
    */
    'system_message' => env('AUTO_ALT_TEXT_SYSTEM_MESSAGE',
        'You are an accessibility expert generating concise, descriptive alt text for images. Focus on the most important visual elements that convey meaning and context. Keep descriptions brief but informative for screen readers. Reply with the alt text only, no introduction or explanations.'
    ),

    /*
    |--------------------------------------------------------------------------
    | Prompt
    |--------------------------------------------------------------------------
    |
    | The prompt sent with each image. Supports Antlers templating:
    | - {{ filename }} - Original filename
    | - {{ basename }} - Filename without extension
    | - {{ extension }} - File extension
    | - {{ width }}, {{ height }} - Image dimensions
    | - {{ orientation }} - 'portrait', 'landscape', or 'square'
    | - {{ container }} - Asset container handle
    | - {{ asset:custom_field }} - Access custom asset fields
    |
    */
    'prompt' => env('AUTO_ALT_TEXT_PROMPT',
        'Describe this image for accessibility alt text.{{ if filename && filename != asset.id }} The filename is "{{ filename }}".{{ /if }}'
    ),

    /*
    |--------------------------------------------------------------------------
    | Generation Parameters
    |--------------------------------------------------------------------------
    */
    'max_tokens' => (int) env('AUTO_ALT_TEXT_MAX_TOKENS', 100),
    'temperature' => (float) env('AUTO_ALT_TEXT_TEMPERATURE', 0.7),
    'timeout' => (int) env('AUTO_ALT_TEXT_TIMEOUT', 60),

    /*
    |--------------------------------------------------------------------------
    | Alt Text Field
    |--------------------------------------------------------------------------
    */
    'alt_text_field' => 'alt',

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    */
    'log_completions' => true,

    /*
    |--------------------------------------------------------------------------
    | Field Action Enabled Fields
    |--------------------------------------------------------------------------
    */
    'action_enabled_fields' => [
        'alt',
        'alt_text',
        'alternative_text',
    ],

    /*
    |--------------------------------------------------------------------------
    | Maximum Image Dimension
    |--------------------------------------------------------------------------
    */
    'max_dimension_pixels' => env('AUTO_ALT_TEXT_MAX_DIMENSION', 2048),

    /*
    |--------------------------------------------------------------------------
    | Automatic Generation Events
    |--------------------------------------------------------------------------
    */
    'automatic_generation_events' => [
        Statamic\Events\AssetUploaded::class,
        Statamic\Events\AssetSaving::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Ignore Patterns
    |--------------------------------------------------------------------------
    */
    'ignore_patterns' => [],

    /*
    |--------------------------------------------------------------------------
    | Individual Asset Ignore Field
    |--------------------------------------------------------------------------
    */
    'ignore_field_handle' => 'auto_alt_text_ignore',

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    */
    'queue' => [
        'connection' => env('AUTO_ALT_TEXT_QUEUE_CONNECTION', config('queue.default')),
        'name' => env('AUTO_ALT_TEXT_QUEUE_NAME', config('queue.connections.'.config('queue.default').'.queue', 'default')),
    ],
];
```

## New Service Implementation

### src/Services/PrismCaptionService.php

```php
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
use Prism\Prism\Enums\Provider;
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
            $base64Image = $this->imageProcessor->processImageToBase64($asset, self::TARGET_FORMAT);

            if (! $base64Image) {
                Log::warning("PrismCaptionService: Could not process image: {$asset->path()}");

                return null;
            }

            $parsedPrompt = $this->parsePrompt($this->config['prompt'], $asset);
            [$provider, $model] = $this->parseModel($this->config['model']);

            $response = Prism::text()
                ->using($provider, $model)
                ->withSystemPrompt($this->config['system_message'])
                ->withPrompt($parsedPrompt, [Image::fromBase64($base64Image)])
                ->withMaxTokens($this->config['max_tokens'])
                ->usingTemperature($this->config['temperature'])
                ->withClientOptions(['timeout' => $this->config['timeout']])
                ->asText();

            $caption = $response->text;

            if (empty($caption)) {
                throw new CaptionGenerationException('AI returned an empty caption.');
            }

            if (config('statamic.auto-alt-text.log_completions', false)) {
                Log::info("PrismCaptionService: Generated caption for {$asset->id()}: {$caption}");
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
                'trace' => $e->getTraceAsString(),
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
     * Parse "provider/model" format into Provider enum and model name.
     *
     * @return array{0: Provider, 1: string}
     */
    private function parseModel(string $model): array
    {
        if (! str_contains($model, '/')) {
            throw new CaptionGenerationException(
                "Invalid model format: '{$model}'. Expected 'provider/model' (e.g., 'openai/gpt-4.1')."
            );
        }

        [$providerName, $modelName] = explode('/', $model, 2);

        $provider = match ($providerName) {
            'openai' => Provider::OpenAI,
            'anthropic' => Provider::Anthropic,
            'ollama' => Provider::Ollama,
            'mistral' => Provider::Mistral,
            'groq' => Provider::Groq,
            'deepseek' => Provider::DeepSeek,
            'xai' => Provider::XAI,
            default => throw new CaptionGenerationException(
                "Unsupported provider: '{$providerName}'. Supported: openai, anthropic, ollama, mistral, groq, deepseek, xai."
            ),
        };

        return [$provider, $modelName];
    }
}
```

## ServiceProvider Changes

### src/ServiceProvider.php

Replace factory-based binding with direct binding:

```php
// Remove:
$this->app->singleton(CaptionServiceFactory::class);
$this->app->bind(CaptionService::class, function ($app) {
    return $app->make(CaptionServiceFactory::class)->make();
});

// Add:
$this->app->bind(CaptionService::class, function ($app) {
    return new PrismCaptionService(
        $app->make(ImageProcessor::class),
        config('statamic.auto-alt-text'),
    );
});
```

## Files to Create

| File | Description |
|------|-------------|
| `src/Services/PrismCaptionService.php` | New unified caption service using Prism |

## Files to Delete

| File | Reason |
|------|--------|
| `src/Services/OpenAIService.php` | Replaced by PrismCaptionService |
| `src/Services/MoondreamService.php` | Moondream not supported by Prism |
| `src/Services/CaptionServiceFactory.php` | No longer needed with single service |

## Files to Modify

| File | Changes |
|------|---------|
| `composer.json` | Add `prism-php/prism` dependency |
| `config/auto-alt-text.php` | Flatten config, use `model` format |
| `src/ServiceProvider.php` | Simplify service binding |
| `tests/*` | Update for new service |
| `README.md` | Document new config and providers |

## Implementation Order

1. Add Prism dependency to `composer.json`
2. Create `PrismCaptionService.php`
3. Update `config/auto-alt-text.php`
4. Update `ServiceProvider.php`
5. Delete old services and factory
6. Update tests
7. Update README

## User Migration Guide

### Before (v0.x)

```env
AUTO_ALT_TEXT_SERVICE=openai
OPENAI_API_KEY=sk-...
OPENAI_MODEL=gpt-4.1
OPENAI_ENDPOINT=https://api.openai.com/v1/chat/completions
```

### After (v1.x)

```env
AUTO_ALT_TEXT_MODEL=openai/gpt-4.1
OPENAI_API_KEY=sk-...
```

API keys are now configured in Prism's config (`config/prism.php`), which uses standard environment variables like `OPENAI_API_KEY`, `ANTHROPIC_API_KEY`, etc.

### Switching Providers

```env
# OpenAI
AUTO_ALT_TEXT_MODEL=openai/gpt-4.1

# Anthropic
AUTO_ALT_TEXT_MODEL=anthropic/claude-sonnet-4-5

# Local Ollama
AUTO_ALT_TEXT_MODEL=ollama/llava

# Groq (fast)
AUTO_ALT_TEXT_MODEL=groq/llava-v1.5-7b-4096-preview
```

## Manual Testing

A test Statamic installation is available for integration testing.

### Test Environment

- **URL:** `http://statamic-auto-alt-text-test.test`
- **Control Panel:** `http://statamic-auto-alt-text-test.test/cp`
- **Credentials:** `claude@claude.ai` / `claude`
- **Logs:** `../statamic-auto-alt-text-test/storage/logs/laravel.log`

### Test Checklist

After implementation, verify:

1. [ ] **Upload test** - Upload a new image, verify alt text is auto-generated
2. [ ] **Field action test** - Use the "Generate Alt Text" action on an existing asset
3. [ ] **Provider switch** - Change `AUTO_ALT_TEXT_MODEL` in `.env`, verify new provider works
4. [ ] **Error handling** - Test with invalid API key, verify graceful error
5. [ ] **Exclusion patterns** - Verify ignore patterns still work
6. [ ] **Queue processing** - Verify jobs dispatch correctly

### Test Commands

```bash
# View logs during testing
tail -f ../statamic-auto-alt-text-test/storage/logs/laravel.log

# Clear config cache after .env changes
cd ../statamic-auto-alt-text-test && php artisan config:clear
```
