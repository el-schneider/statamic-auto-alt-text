<?php

declare(strict_types=1);

namespace ElSchneider\StatamicAutoAltText\Events;

use Statamic\Assets\Asset;

final class BeforeCaptionGeneration
{
    public function __construct(
        public readonly Asset $asset
    ) {}
}
