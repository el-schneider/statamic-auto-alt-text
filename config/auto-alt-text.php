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
    'service' => 'moondream',

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
            'model' => env('OPENAI_MODEL', 'gpt-4-turbo'), // Or 'gpt-4o' or other vision models
            'endpoint' => env('OPENAI_ENDPOINT', 'https://api.openai.com/v1/chat/completions'),
            'prompt' => env('OPENAI_PROMPT', 'Describe this image concisely for accessibility alt text.'),
            'max_tokens' => (int) env('OPENAI_MAX_TOKENS', 100),
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
    'max_dimension_pixels' => env('AUTO_ALT_TEXT_MAX_DIMENSION', 1920), // Default to 1920px

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
