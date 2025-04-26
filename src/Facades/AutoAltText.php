<?php

declare(strict_types=1);

namespace ElSchneider\StatamicAutoAltText\Facades;

use Illuminate\Support\Facades\Facade;
use Statamic\Assets\Asset;

/**
 * @method static string|null generateCaption(Asset $asset, ?string $field = null)
 * @method static array generateCaptions(array $assets, ?string $field = null)
 *
 * @see \ElSchneider\StatamicAutoAltText\StatamicAutoAltText
 */
final class AutoAltText extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'auto-alt-text';
    }
}
