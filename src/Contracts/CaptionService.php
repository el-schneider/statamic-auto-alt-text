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
     * Check if the service supports generating a caption for the given asset type.
     *
     * @param  Asset  $asset  The asset to check.
     * @return bool True if the asset type is supported, false otherwise.
     */
    public function supportsAssetType(Asset $asset): bool;
}
