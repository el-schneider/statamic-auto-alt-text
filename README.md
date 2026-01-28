<img src="images/aat_banner.png" alt="Auto Alt Text">

# Statamic Auto Alt Text

> Automatically generate descriptive alt text for images in Statamic v5 using AI services via [Prism PHP](https://github.com/prism-php/prism)

## ⚠️ Upgrading from v0.x?

**Breaking Changes in v1.0:** Moondream support has been removed. If you were using Moondream, switch to [Ollama](https://ollama.ai) with a vision model like `llava` for local processing, or choose from 7+ cloud providers (OpenAI, Anthropic, etc.).

**Migration is simple:** Replace your old config with the new `AUTO_ALT_TEXT_MODEL` format:

```dotenv
# Old (v0.x)
AUTO_ALT_TEXT_SERVICE=openai
OPENAI_MODEL=gpt-4.1

# New (v1.x)
AUTO_ALT_TEXT_MODEL=openai/gpt-4.1
```

See [Upgrading from v0.x](#upgrading-from-v0x) for full details.

## Features

- **Automatic Generation:** Generate alt text for assets using AI by listening for Statamic asset events
- **Multiple AI Providers:** Support for OpenAI, Anthropic, Ollama, Mistral, Groq, DeepSeek, and xAI via Prism PHP
- **Data Privacy:** Option to use local Ollama endpoints, keeping image data within your infrastructure
- **Asset Filtering:** Exclude sensitive or private assets from processing with global patterns, container-specific rules, or individual asset settings
- **Control Panel Integration:** Field Action to generate alt text for individual images
- **Bulk Processing:** Artisan Command for processing images individually or in batch

## Installation

```bash
composer require el-schneider/statamic-auto-alt-text
```

Publish the configuration file:

```bash
php artisan vendor:publish --tag="statamic-auto-alt-text-config"
```

## Configuration

Configure your AI provider in `.env`:

```dotenv
# Model format: provider/model-name
AUTO_ALT_TEXT_MODEL=openai/gpt-4.1

# API key (managed by Prism - see config/prism.php)
OPENAI_API_KEY=your_api_key_here
```

### Supported Providers

| Provider | Example Model | Environment Variable |
|----------|--------------|---------------------|
| OpenAI | `openai/gpt-4.1` | `OPENAI_API_KEY` |
| Anthropic | `anthropic/claude-sonnet-4-5` | `ANTHROPIC_API_KEY` |
| Ollama | `ollama/llava` | (local, no key needed) |
| Mistral | `mistral/pixtral-large-latest` | `MISTRAL_API_KEY` |
| Groq | `groq/llava-v1.5-7b-4096-preview` | `GROQ_API_KEY` |
| DeepSeek | `deepseek/deepseek-chat` | `DEEPSEEK_API_KEY` |
| xAI | `xai/grok-vision-beta` | `XAI_API_KEY` |

### Custom Providers

You can use any custom provider supported by Prism. First, register your custom provider in a service provider:

```php
use App\Prism\Providers\MyCustomProvider;

public function boot(): void
{
    $this->app['prism-manager']->extend('my-provider', function ($app, $config) {
        return new MyCustomProvider(apiKey: $config['api_key']);
    });
}
```

Then configure it in your `.env`:

```dotenv
AUTO_ALT_TEXT_MODEL=my-provider/model-name
```

See [Prism's Custom Providers documentation](https://prismphp.com/advanced/custom-providers.html) for details on building and registering custom providers.

For advanced provider configuration (custom endpoints, timeouts, etc.), publish Prism's config:

```bash
php artisan vendor:publish --tag="prism-config"
```

### Non-English Captions

You can customize the prompt to generate captions in your preferred language:

```dotenv
AUTO_ALT_TEXT_PROMPT="Beschreibe dieses Bild kurz und bündig, um einen Alternativtext für Barrierefreiheit bereitzustellen."
AUTO_ALT_TEXT_SYSTEM_MESSAGE="Du bist ein Barrierefreiheitsexperte, der kurze, beschreibende Alt-Texte für Bilder generiert. Antworte nur mit dem Alt-Text, ohne Einleitung oder Erklärungen."
```

## Usage

### Automatic Generation

The addon listens for configured Statamic events (default: `AssetUploaded` and `AssetSaving`). When an asset without alt text is detected, a background job is dispatched to generate it automatically using your configured AI service.

> **Important:** This feature requires Laravel's queue system with an asynchronous queue driver (e.g., `database`, `redis`, `sqs`) and a running queue worker (`php artisan queue:work`). The default `sync` driver won't work for background processing.

Optionally customize the queue configuration:

```dotenv
# Optional: Defaults to your application's default queue connection
AUTO_ALT_TEXT_QUEUE_CONNECTION=redis

# Optional: Defaults to the default queue name for the connection
AUTO_ALT_TEXT_QUEUE_NAME=alt_text_generation
```

### Manual Generation

For existing assets or specific workflows:

1. **Field Action:** Edit an asset, find the `alt` text field, and click the "Generate Alt Text" action
2. **Statamic Action:** In an Asset container, use the "Generate Alt Text" action from the contextual menu
3. **CLI Command:** Process assets in bulk with:

```bash
php please auto-alt:generate
```

See `php please auto-alt:generate --help` for options to specify containers, assets, and overwriting behavior

### Using Local Ollama

For privacy or compliance reasons, you can run vision models locally with Ollama:

1. **Set up Ollama:** Install [Ollama](https://ollama.ai) and pull a vision model: `ollama pull llava`
2. **Configure the addon:**

```dotenv
AUTO_ALT_TEXT_MODEL=ollama/llava
```

3. **Configure Prism:** Publish Prism's config and set the Ollama endpoint:

```bash
php artisan vendor:publish --tag="prism-config"
```

Then update `config/prism.php`:

```php
'ollama' => [
    'url' => env('OLLAMA_URL', 'http://localhost:11434'),
],
```

## Generation Parameters

Fine-tune the AI generation with these environment variables:

```dotenv
AUTO_ALT_TEXT_MAX_TOKENS=100      # Maximum length of generated text
AUTO_ALT_TEXT_TEMPERATURE=0.7     # Creativity (0.0 = deterministic, 1.0 = creative)
AUTO_ALT_TEXT_TIMEOUT=60          # Request timeout in seconds
```

## Upgrading from v0.x

If upgrading from v0.x, update your environment variables:

### Before (v0.x)

```dotenv
AUTO_ALT_TEXT_SERVICE=openai
OPENAI_API_KEY=sk-...
OPENAI_MODEL=gpt-4.1
OPENAI_ENDPOINT=https://api.openai.com/v1/chat/completions
```

### After (v1.x)

```dotenv
AUTO_ALT_TEXT_MODEL=openai/gpt-4.1
OPENAI_API_KEY=sk-...
```

**Breaking changes:**
- Moondream support has been removed (use Ollama with llava for local processing)
- Config structure simplified: `services.openai.*` replaced with flat `model` setting
- Environment variables changed: `AUTO_ALT_TEXT_MODEL` replaces `AUTO_ALT_TEXT_SERVICE`
