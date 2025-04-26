<?php

declare(strict_types=1);

namespace ElSchneider\StatamicAutoAltText\Services;

use ElSchneider\StatamicAutoAltText\Contracts\CaptionService;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

final class CaptionServiceFactory
{
    public function __construct(
        private readonly Container $container
    ) {}

    public function make(): CaptionService
    {
        $service = config('auto-alt-text.service', 'moondream');

        return match ($service) {
            'moondream' => $this->container->make(MoondreamService::class),
            default => throw new InvalidArgumentException("Unsupported caption service: {$service}"),
        };
    }
}
