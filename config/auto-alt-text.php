<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Default Caption Service
    |--------------------------------------------------------------------------
    |
    | The default service to use for generating image captions.
    | Currently supported: 'moondream', 'openai'
    |
    */
    'service' => env('AUTO_ALT_TEXT_SERVICE', 'moondream'),

    /*
    |--------------------------------------------------------------------------
    | Service Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration options for the available caption services.
    |
    */
    'services' => [
        'moondream' => [
            'endpoint' => env('MOONDREAM_ENDPOINT', 'https://api.moondream.ai/v1/caption'),
            'api_key' => env('MOONDREAM_API_KEY'),
            'options' => [
                'length' => 'short',
                'stream' => false,
            ],
        ],
        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
            'model' => env('OPENAI_MODEL', 'gpt-4.1-mini'), // Or 'gpt-4o' or other vision models
            'endpoint' => env('OPENAI_ENDPOINT', 'https://api.openai.com/v1/chat/completions'),
            'system_message' => env('OPENAI_SYSTEM_MESSAGE', 'You are an accessibility expert generating concise, descriptive alt text for images. Focus on the most important visual elements that convey meaning and context. Keep descriptions brief but informative for screen readers.'),
            'prompt' => env('OPENAI_PROMPT', 'Describe this image for accessibility alt text.'),
            'max_tokens' => (int) env('OPENAI_MAX_TOKENS', 100),
            'detail' => env('OPENAI_DETAIL', 'auto'), // 'low', 'high', or 'auto'
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Alt Text Field
    |--------------------------------------------------------------------------
    |
    | The field name where alt text should be stored on assets.
    | This typically defaults to 'alt'.
    |
    */
    'alt_text_field' => 'alt',

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    |
    | Whether to log successful caption generations and errors.
    | Useful for debugging.
    |
    */
    'log_completions' => true,

    /*
    |--------------------------------------------------------------------------
    | Field Action Enabled Fields
    |--------------------------------------------------------------------------
    |
    | List of field handles where the 'Generate Alt Text' action should appear
    | in the Control Panel (controlled via JavaScript).
    |
    */
    'action_enabled_fields' => [
        'alt',
        'alt_text',
        'alternative_text',
    ],

    /*
    |--------------------------------------------------------------------------
    | Maximum Image Dimension for Captioning
    |--------------------------------------------------------------------------
    |
    | Specify the maximum dimension (width or height) in pixels for images
    | sent to the captioning service. Images exceeding this will be resized
    | proportionally before processing to reduce payload size and cost.
    | Set to null to disable resizing.
    |
    */
    'max_dimension_pixels' => env('AUTO_ALT_TEXT_MAX_DIMENSION', 2048), // Default to 2048px

    /*
    |--------------------------------------------------------------------------
    | Automatic Generation Events
    |--------------------------------------------------------------------------
    |
    | Specify the fully qualified class names of Statamic asset events that
    | should trigger automatic alt text generation if the alt text field
    | is empty.
    |
    | Supported events (examples):
    | - \Statamic\Events\AssetUploaded::class
    | - \Statamic\Events\AssetSaving::class // Fired before asset is saved
    |
    | Leave empty to disable automatic generation on events.
    |
    */
    'automatic_generation_events' => [
        Statamic\Events\AssetUploaded::class,
        Statamic\Events\AssetSaving::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Ignore Patterns
    |--------------------------------------------------------------------------
    |
    | Configure patterns to exclude certain assets from alt text generation.
    | Useful for excluding portraits for privacy reasons, non-descriptive
    | images, or file types that don't need alt text.
    |
    | Supports glob patterns: *, ?, [abc], {jpg,jpeg}
    | Patterns are matched against the asset's path relative to container root.
    |
    | Syntax:
    | - 'pattern' - Global pattern applied to all containers
    | - 'container::pattern' - Pattern applied only to specific container
    |
    | Examples:
    | - 'portraits/*' - exclude all files in portraits folder (privacy)
    | - 'logos/*' - exclude logo/branding images (non-descriptive)
    | - 'temp/*' - exclude temporary files
    | - 'assets::staff-photos/*' - exclude staff photos only in assets container
    | - 'gallery::private/*' - exclude private folder only in gallery container
    | - 'assets::screenshots/*.png' - exclude PNG screenshots in assets container
    |
    */
    'ignore_patterns' => [
        // Global patterns (applied to all containers)
        // 'portraits/*',
        // 'logos/*',
        // 'temp/*',

        // Container-specific patterns
        // 'assets::staff-photos/*',
        // 'gallery::private/*',
        // 'documents::screenshots/*',
    ],

    /*
    |--------------------------------------------------------------------------
    | Individual Asset Ignore Field
    |--------------------------------------------------------------------------
    |
    | Field handle to check on individual assets for exclusion from alt text
    | generation. If this field exists on an asset and has a truthy value,
    | the asset will be excluded from processing.
    |
    | This provides fine-grained control for individual assets beyond the
    | pattern-based exclusions above. Useful for excluding specific images
    | that require manual attention or should never have auto-generated alt text.
    |
    | To use this feature:
    | 1. Add a toggle field with this handle to your asset blueprints
    | 2. Set the field to true/on for assets you want to exclude
    | 3. The field can be any type - any truthy value will exclude the asset
    |
    | Set to null to disable individual asset exclusion.
    |
    */
    'ignore_field_handle' => 'auto_alt_text_ignore',

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the queue connection and name for background jobs dispatched
    | by this addon (e.g., automatic generation, bulk generation).
    | Defaults are taken from your application's default queue settings.
    |
    */
    'queue' => [
        'connection' => env('AUTO_ALT_TEXT_QUEUE_CONNECTION', config('queue.default')),
        'name' => env('AUTO_ALT_TEXT_QUEUE_NAME', config('queue.connections.'.config('queue.default').'.queue', 'default')),
    ],
];
