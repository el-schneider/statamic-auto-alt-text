<?php

declare(strict_types=1);

namespace ElSchneider\StatamicAutoAltText;

use ElSchneider\StatamicAutoAltText\Actions\GenerateAltText;
use Statamic\Assets\Asset;

final class StatamicAutoAltText
{
    public function __construct(
        private readonly GenerateAltText $generateAltText
    ) {}

    /**
     * Generate caption for a single asset
     */
    public function generateCaption(Asset $asset, ?string $field = null): ?string
    {
        return $this->generateAltText->handle($asset, $field);
    }

    /**
     * Generate captions for multiple assets
     */
    public function generateCaptions(array $assets, ?string $field = null): array
    {
        return $this->generateAltText->handleBatch($assets, $field);
    }
}
