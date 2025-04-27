# Statamic Auto Alt Text

Statamic Auto Alt Text is an addon for Statamic v5 that automatically generates descriptive alt text for images using the Moondream AI service. This helps improve website accessibility and saves content editors time.

## Features

- Automatically generates alt text for assets using Moondream AI (supports cloud and self-hosted endpoints).
- Provides a Control Panel (CP) field action to generate alt text for individual images.
- Includes an Artisan command (`php please alt:generate`) for bulk processing images.
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
2.  **CLI Command:** Run `php artisan auto-alt:generate` to process assets in bulk. See `php artisan auto-alt:generate --help` for options like specifying containers, assets, batch size, and overwriting existing text.

## Configuration

Configure the Moondream service (API keys, endpoints for cloud/local) and other settings in `config/statamic/auto-alt-text.php` after publishing the configuration.
