<?php

declare(strict_types=1);

namespace ElSchneider\StatamicAutoAltText\FieldActions;

use ElSchneider\StatamicAutoAltText\Contracts\CaptionService;
use ElSchneider\StatamicAutoAltText\StatamicAutoAltText;
use Statamic\Actions\Action;
use Statamic\Contracts\Assets\Asset as AssetContract;
use Statamic\Facades\Asset;

final class GenerateAltTextAction extends Action
{
    public function __construct(
        private readonly CaptionService $captionService,
        private readonly StatamicAutoAltText $autoAltText
    ) {
        parent::__construct();
    }

    public static function title(): string
    {
        return __('auto-alt-text::messages.generate_alt_text_action');
    }

    /**
     * Run the action for the given items.
     */
    public function run($items, $values): void
    {

        foreach ($items as $item) {
            $asset = Asset::find($item);

            if ($asset) {
                $this->autoAltText->dispatchGenerationJob($asset);
            }
        }
    }

    // Authorization for bulk action use
    public function authorize($user, $item): bool
    {
        return $user->can('update', $item);
    }

    /**
     * Visibility for bulk actions context.
     * The actual field-level visibility is controlled by JS.
     */
    public function visibleTo($item): bool
    {
        if (! $item instanceof AssetContract) {
            return false;
        }

        // Only show for supported image assets based on the configured service
        return $this->captionService->supportsAssetType($item);
    }
}
