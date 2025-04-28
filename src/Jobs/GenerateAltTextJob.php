<?php

declare(strict_types=1);

namespace ElSchneider\StatamicAutoAltText\Jobs;

use ElSchneider\StatamicAutoAltText\Actions\GenerateAltText;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Statamic\Assets\Asset;

final class GenerateAltTextJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private readonly Asset $asset,
        private readonly ?string $field = null,
        private readonly bool $saveQuietly = false
    ) {
        // Automatically set queue if needed
        // $this->onQueue('alt-text-generation');
    }

    public function handle(GenerateAltText $generateAltText): void
    {
        $generateAltText->handle($this->asset, $this->field, $this->saveQuietly);
    }
}
