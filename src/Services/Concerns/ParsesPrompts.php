<?php

declare(strict_types=1);

namespace ElSchneider\StatamicAutoAltText\Services\Concerns;

use Statamic\Assets\Asset;
use Statamic\Facades\Antlers;

trait ParsesPrompts
{
    /**
     * Parse a prompt template with Antlers templating using asset data.
     */
    protected function parsePrompt(string $prompt, Asset $asset): string
    {
        return (string) Antlers::parse($prompt, $this->buildTemplateData($asset));
    }

    /**
     * Build template data array for Antlers parsing with asset properties.
     */
    protected function buildTemplateData(Asset $asset): array
    {
        return [
            'asset' => $asset->augmented(),
            'filename' => $asset->filename(),
            'basename' => $asset->basename(),
            'extension' => $asset->extension(),
            'width' => $asset->width(),
            'height' => $asset->height(),
            'orientation' => $asset->orientation(),
            'container' => $asset->container()->handle(),
            'mime_type' => $asset->mimeType(),
            'size' => $asset->size(),
            'path' => $asset->path(),
            'url' => $asset->url(),
        ];
    }
}
