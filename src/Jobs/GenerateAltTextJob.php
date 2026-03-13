<?php

declare(strict_types=1);

namespace ElSchneider\StatamicAutoAltText\Jobs;

use ElSchneider\StatamicAutoAltText\Actions\GenerateAltText;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Statamic\Assets\Asset;
use Throwable;

final class GenerateAltTextJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout;

    public function __construct(
        private readonly Asset $asset,
        private readonly ?string $field = null,
        private readonly bool $saveQuietly = false
    ) {
        $this->timeout = (int) config('statamic.auto-alt-text.timeout', 60) + 30;
    }

    public function handle(GenerateAltText $generateAltText): void
    {
        $generateAltText->handle($this->asset, $this->field, $this->saveQuietly);
    }

    public function failed(Throwable $exception): void
    {
        $field = $this->field ?? config('statamic.auto-alt-text.alt_text_field', 'alt');
        $cacheKey = "alt_text_error_{$this->asset->id()}_{$field}";

        Cache::put($cacheKey, $exception->getMessage(), now()->addMinutes(5));
    }
}
