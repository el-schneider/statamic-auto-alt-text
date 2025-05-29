<?php

declare(strict_types=1);

namespace ElSchneider\StatamicAutoAltText\Actions;

use ElSchneider\StatamicAutoAltText\Services\CaptionServiceFactory;
use Illuminate\Support\Str;
use Statamic\Assets\Asset;

final class GenerateAltText
{
    public function __construct(
        private readonly CaptionServiceFactory $serviceFactory
    ) {}

    /**
     * Generate alt text for a single asset and save it
     */
    public function handle(Asset $asset, ?string $field = null, bool $saveQuietly = false): ?string
    {
        $service = $this->serviceFactory->make();
        $caption = $service->generateCaption($asset);

        if ($caption) {
            $caption = $this->sanitizeCaption($caption);

            $fieldName = $field ?? config('statamic.auto-alt-text.alt_text_field', 'alt');
            $asset->set($fieldName, $caption);

            $saveQuietly ? $asset->saveQuietly() : $asset->save();
        }

        return $caption;
    }

    /**
     * Sanitize the caption by removing HTML tags, decoding entities, and normalizing whitespace
     */
    private function sanitizeCaption(string $caption): string
    {
        // Decode HTML entities
        $caption = html_entity_decode($caption, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Strip HTML tags
        $caption = strip_tags($caption);

        // Trim and normalize whitespace (removes extra spaces, tabs, newlines)
        $caption = Str::squish($caption);

        return $caption;
    }
}
