<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Default Caption Service
    |--------------------------------------------------------------------------
    |
    | The default service to use for generating image captions.
    | Currently supported: 'moondream'
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
            // Whether to use the cloud API or a local endpoint
            'mode' => env('MOONDREAM_MODE', 'cloud'), // 'cloud' or 'local'

            // Cloud API configuration
            'cloud' => [
                'api_key' => env('MOONDREAM_API_KEY'),
                'endpoint' => env('MOONDREAM_CLOUD_ENDPOINT', 'https://api.moondream.ai/v1/caption'),
                'options' => [
                    'length' => 'short',
                    'stream' => false,
                ],
            ],

            // Local endpoint configuration
            'local' => [
                'endpoint' => env('MOONDREAM_LOCAL_ENDPOINT', 'http://localhost:2020/v1/caption'),
                'options' => [
                    'length' => 'short',
                    'stream' => false,
                ],
            ],
        ],
        // Add other services here in the future
        // 'another_service' => [...],
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
    | Batch Processing Size
    |--------------------------------------------------------------------------
    |
    | Default batch size for processing multiple assets via the CLI command.
    |
    */
    'batch_size' => 50,

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
    | Caption Service
    |--------------------------------------------------------------------------
    |
    | Configure the caption generation service. Currently, only 'moondream'
    | is supported. You can specify the API endpoint for the service.
    |
    */
    'caption_service' => [
        'driver' => env('AUTO_ALT_TEXT_DRIVER', 'moondream'),

        'moondream' => [
            'endpoint' => env('MOONDREAM_ENDPOINT'),
        ],
    ],

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
