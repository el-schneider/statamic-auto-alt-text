<?php

declare(strict_types=1);

namespace ElSchneider\StatamicAutoAltText;

use ElSchneider\StatamicAutoAltText\Actions\GenerateAltText;
use ElSchneider\StatamicAutoAltText\Jobs\GenerateAltTextJob;
use Statamic\Assets\Asset;

final class StatamicAutoAltText
{
    public function __construct(
        private readonly GenerateAltText $generateAltText
    ) {}

    /**
     * Generate caption for a single asset (synchronously)
     */
    public function generateCaption(Asset $asset, ?string $field = null): ?string
    {
        return $this->generateAltText->handle($asset, $field);
    }

    /**
     * Dispatch a job to generate alt text for an asset using configured queue settings.
     *
     * @param  bool  $saveQuietly  Whether the job should save the asset without triggering events.
     */
    public function dispatchGenerationJob(Asset $asset, bool $saveQuietly = false): void
    {
        $queueConfig = config('statamic.auto-alt-text.queue', []);
        $queueConnection = $queueConfig['connection'] ?? null;
        $queueName = $queueConfig['name'] ?? null;

        GenerateAltTextJob::dispatch($asset, null, $saveQuietly)
            ->onConnection($queueConnection)
            ->onQueue($queueName);
    }
}
