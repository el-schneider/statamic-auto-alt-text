# Statamic Auto Alt Text

Statamic Auto Alt Text is an addon for Statamic v5 that automatically generates descriptive alt text for images using AI services. It currently supports **Moondream** (cloud or self-hosted) and **OpenAI** (GPT-4 Vision models). This helps improve website accessibility and saves content editors time.

## Features

- Automatically generates alt text for assets using AI (Moondream or OpenAI).
- Provides a Control Panel (CP) field action to generate alt text for individual images.
- Includes an Artisan command (`php please auto-alt:generate`) for processing images individually.
- Dispatches events (`BeforeCaptionGeneration`, `AfterCaptionGeneration`) for extensibility.
- Configurable via `config/auto-alt-text.php`.

## How to Install

Install the addon via Composer:

```bash
composer require el-schneider/auto-alt-text
```

Publish the configuration file:

```bash
php artisan vendor:publish --tag="statamic-auto-alt-text-config"
```

## How to Use

1.  **Field Action:** Navigate to an Asset field in the CP, find the `alt` text field, and click the "Generate Alt Text" action.
2.  **Statamic Action:** Navigate to an Asset container and find the "Generate Alt Text" action in the contextual menu.
3.  **CLI Command:** Run `php artisan auto-alt:generate` to process assets individually (e.g., after uploading a batch). See `php artisan auto-alt:generate --help` for options like specifying containers, assets, and overwriting existing text.

## Configuration

Configure the addon via `config/statamic/auto-alt-text.php` after publishing the configuration file.

Key configuration options:

- `service`: Choose the default AI service ('moondream' or 'openai'). Set via `AUTO_ALT_TEXT_SERVICE` env variable.
- `services.moondream`: Configure Moondream API keys, endpoints (cloud/local), and options.
- `services.openai`: Configure OpenAI API key (`OPENAI_API_KEY`), model (`OPENAI_MODEL`), endpoint, prompt, and max tokens.
- `alt_text_field`: The asset field handle to store the generated alt text (default: 'alt').
- `max_dimension_pixels`: Maximum image dimension for resizing before sending to Moondream (only affects Moondream).
- `log_completions`: Enable/disable logging.
- `action_enabled_fields`: Field handles where the CP action should appear.
