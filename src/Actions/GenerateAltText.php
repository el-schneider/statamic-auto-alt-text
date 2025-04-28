<?php

declare(strict_types=1);

namespace ElSchneider\StatamicAutoAltText\Actions;

use ElSchneider\StatamicAutoAltText\Services\CaptionServiceFactory;
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
            $fieldName = $field ?? config('statamic.auto-alt-text.alt_text_field', 'alt');
            $asset->set($fieldName, $caption);

            $saveQuietly ? $asset->saveQuietly() : $asset->save();
        }

        return $caption;
    }
}
