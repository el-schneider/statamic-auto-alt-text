<?php

declare(strict_types=1);

namespace ElSchneider\StatamicAutoAltText\Events;

use Statamic\Assets\Asset;

final class AfterCaptionGeneration
{
    public function __construct(
        public readonly Asset $asset,
        public readonly ?string $caption
    ) {}
}
