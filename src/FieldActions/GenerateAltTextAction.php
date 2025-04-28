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
    private CaptionService $captionService;

    private StatamicAutoAltText $autoAltText; // Inject main addon class

    public function __construct(CaptionService $captionService, StatamicAutoAltText $autoAltText)
    {
        $this->captionService = $captionService;
        $this->autoAltText = $autoAltText;
        parent::__construct();
    }

    public static function title(): string
    {
        // Using translation key for potential localization
        return __('auto-alt-text::messages.generate_alt_text_action');
    }

    /**
     * Run the action for the given items.
     * This now dispatches a job using the centralized helper.
     */
    public function run($items, $values): void
    {
        $fieldName = $this->fieldHandle();

        foreach ($items as $item) {
            $asset = Asset::find($item);
            // Pass field name to the job if necessary, though the job currently doesn't use it.
            // If the job needs the field name, the helper and job must be updated.
            if ($asset) {
                $this->autoAltText->dispatchGenerationJob($asset);
            }
        }
    }

    // Authorization for bulk action use
    public function authorize($user, $item): bool
    {
        // Allow if user can update the specific asset
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

    /**
     * Get the field handle from the context.
     */
    private function fieldHandle(): string
    {
        return $this->context['field']['handle'] ?? config('statamic.auto-alt-text.alt_text_field', 'alt');
    }

    // Optional: Define fields if the action needs configuration in bulk mode
    // public function blueprint() { ... }
}
