<?php

declare(strict_types=1);

namespace ElSchneider\StatamicAutoAltText\Listeners;

use ElSchneider\StatamicAutoAltText\StatamicAutoAltText;
use Illuminate\Support\Facades\Log;
use Statamic\Assets\Asset;

final class HandleAssetEvent
{
    public function __construct(
        private readonly StatamicAutoAltText $autoAltText
    ) {}

    /**
     * Handle the event.
     */
    public function handle($event): void
    {
        $config = config('statamic.auto-alt-text');
        $enabledEvents = $config['automatic_generation_events'] ?? [];
        $eventClass = get_class($event);

        // Check if automatic generation is enabled for this event type
        if (! in_array($eventClass, $enabledEvents, true)) {
            return;
        }

        $asset = $this->getAssetFromEvent($event);
        if (! $asset) {
            Log::warning("Asset not found in event: {$eventClass}");

            return;
        }

        $altTextField = $config['alt_text_field'] ?? 'alt';

        // Check if alt text is already present
        if (! empty($asset->get($altTextField))) {
            return;
        }

        // Use the helper method to dispatch the job, saving quietly
        $this->autoAltText->dispatchGenerationJob($asset, saveQuietly: true);
    }

    /**
     * Get the Asset object from the event payload.
     *
     * Handles potential differences in how events store the asset.
     */
    private function getAssetFromEvent($event): ?Asset
    {
        // Check common property names, add more if needed for other events
        if (isset($event->asset) && $event->asset instanceof Asset) {
            return $event->asset;
        }

        // Add checks for other potential property names or methods if necessary
        // e.g., if (method_exists($event, 'getAsset') && $event->getAsset() instanceof Asset)

        return null;
    }
}
