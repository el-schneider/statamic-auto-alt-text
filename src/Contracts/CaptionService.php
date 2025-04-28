<?php

declare(strict_types=1);

namespace ElSchneider\StatamicAutoAltText\Contracts;

use ElSchneider\StatamicAutoAltText\Exceptions\CaptionGenerationException;
use Statamic\Assets\Asset;

interface CaptionService
{
    /**
     * Generate a caption for a single asset
     *
     * @param  Asset  $asset  The asset to generate a caption for
     * @return string|null The generated caption or null on failure
     *
     * @throws CaptionGenerationException
     */
    public function generateCaption(Asset $asset): ?string;

    /**
     * Generate captions for multiple assets
     *
     * @param  array<Asset>  $assets  The assets to process
     * @return array<string, string|null> Asset IDs mapped to their captions (null for failures)
     */
    public function generateCaptions(array $assets): array;

    /**
     * Check if the service supports generating a caption for the given asset type.
     *
     * @param  Asset  $asset  The asset to check.
     * @return bool True if the asset type is supported, false otherwise.
     */
    public function supportsAssetType(Asset $asset): bool;
}
