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

    public function make(?string $serviceName = null): CaptionService
    {
        $service = $serviceName ?? config('statamic.auto-alt-text.service', 'moondream');
        $config = config("statamic.auto-alt-text.services.{$service}");

        return match ($service) {
            'moondream' => $this->container->make(MoondreamService::class, ['config' => $config]),
            'openai' => $this->container->make(OpenAIService::class, ['config' => $config]),
            default => throw new InvalidArgumentException("Unsupported caption service: {$service}"),
        };
    }
}
