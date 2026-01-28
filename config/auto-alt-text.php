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
    | Examples:
    | - openai/gpt-4.1 (default)
    | - anthropic/claude-sonnet-4-5
    | - ollama/llava
    | - mistral/pixtral-large-latest
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
    'log_completions' => (bool) env('AUTO_ALT_TEXT_LOG_COMPLETIONS', false),

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
