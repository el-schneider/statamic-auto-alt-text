<?php

declare(strict_types=1);

namespace ElSchneider\StatamicAutoAltText\Services;

use Exception;
use Illuminate\Support\Facades\Log;
use Statamic\Assets\Asset;
use Statamic\Facades\Glide;
use Statamic\Imaging\ImageGenerator;

final class ImageProcessor
{
    private ?int $maxDimensionPixels;

    private ImageGenerator $imageGenerator;

    public function __construct(ImageGenerator $imageGenerator)
    {
        $this->maxDimensionPixels = config('statamic.auto-alt-text.max_dimension_pixels');
        $this->imageGenerator = $imageGenerator;
    }

    public function processImageToBase64(Asset $asset, string $targetFormat = 'jpeg'): ?string
    {
        try {
            $params = $this->buildGlideParams($asset, $targetFormat);

            $cachedPath = $this->imageGenerator->generateByAsset($asset, $params);

            $cache = Glide::cacheDisk();
            $content = $cache->get($cachedPath);

            $mimeType = $this->getMimeTypeForFormat($targetFormat);

            return 'data:'.$mimeType.';base64,'.base64_encode($content);

        } catch (Exception $e) {
            Log::error("Error processing image {$asset->path()}: {$e->getMessage()}");
            Log::error($e->getTraceAsString());

            return null;
        }
    }

    private function buildGlideParams(Asset $asset, string $targetFormat): array
    {
        $params = ['fm' => $this->normalizeFormat($targetFormat)];

        // Add quality for JPEG
        if (in_array($targetFormat, ['jpeg', 'jpg'])) {
            $params['q'] = 85;
        }

        // Add resizing if configured
        if ($this->maxDimensionPixels !== null) {
            $width = $asset->width();
            $height = $asset->height();

            if ($width && $height && ($width > $this->maxDimensionPixels || $height > $this->maxDimensionPixels)) {
                if ($width > $height) {
                    $params['w'] = $this->maxDimensionPixels;
                } else {
                    $params['h'] = $this->maxDimensionPixels;
                }
                $params['fit'] = 'contain';
            }
        }

        return $params;
    }

    private function normalizeFormat(string $format): string
    {
        $format = mb_strtolower($format);

        return match ($format) {
            'jpg' => 'jpeg',
            default => $format
        };
    }

    private function getMimeTypeForFormat(string $format): string
    {
        return match (mb_strtolower($format)) {
            'jpeg', 'jpg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            default => 'image/jpeg'
        };
    }
}
