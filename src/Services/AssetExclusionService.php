<?php

declare(strict_types=1);

namespace ElSchneider\StatamicAutoAltText\Services;

use Statamic\Assets\Asset;

final class AssetExclusionService
{
    /**
     * Check if an asset should be excluded from alt text generation
     */
    public function shouldExclude(Asset $asset): bool
    {
        $patterns = config('statamic.auto-alt-text.ignore_patterns', []);

        if (empty($patterns)) {
            return false;
        }

        $containerHandle = $asset->containerHandle();
        $assetPath = $asset->path();

        foreach ($patterns as $pattern) {
            if (! is_string($pattern)) {
                continue;
            }

            // Check if pattern has container prefix (container::path)
            if (str_contains($pattern, '::')) {
                [$patternContainer, $patternPath] = explode('::', $pattern, 2);

                // Only check if container matches
                if ($patternContainer === $containerHandle) {
                    if ($this->matchesPattern($assetPath, $patternPath)) {
                        return true;
                    }
                }
            } else {
                // Global pattern (no container prefix)
                if ($this->matchesPattern($assetPath, $pattern)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check if a path matches a single pattern
     */
    private function matchesPattern(string $path, string $pattern): bool
    {
        // Use fnmatch for glob pattern matching
        // FNM_CASEFOLD makes it case-insensitive for better file extension matching
        return fnmatch($pattern, $path, FNM_CASEFOLD);
    }
}
