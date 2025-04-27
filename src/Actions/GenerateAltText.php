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
    public function handle(Asset $asset, ?string $field = null): ?string
    {
        $service = $this->serviceFactory->make();
        $caption = $service->generateCaption($asset);

        if ($caption) {
            $fieldName = $field ?? config('statamic.auto-alt-text.alt_text_field', 'alt');
            $asset->set($fieldName, $caption);
            $asset->save();
        }

        return $caption;
    }

    /**
     * Generate alt text for multiple assets
     */
    public function handleBatch(array $assets, ?string $field = null): array
    {
        $results = [];
        $fieldName = $field ?? config('statamic.auto-alt-text.alt_text_field', 'alt');
        $service = $this->serviceFactory->make();

        $captions = $service->generateCaptions($assets);

        foreach ($assets as $asset) {
            $caption = $captions[$asset->id()] ?? null;

            if ($caption) {
                $asset->set($fieldName, $caption);
                $asset->save();
                $results[$asset->id()] = $caption;
            } else {
                $results[$asset->id()] = null;
            }
        }

        return $results;
    }
}
