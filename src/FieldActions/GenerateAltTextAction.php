<?php

declare(strict_types=1);

namespace ElSchneider\StatamicAutoAltText\FieldActions;

use ElSchneider\StatamicAutoAltText\Jobs\GenerateAltTextJob;
use Statamic\Actions\Action;
use Statamic\Contracts\Assets\Asset as AssetContract;
use Statamic\Facades\Asset;

final class GenerateAltTextAction extends Action
{
    public static function title(): string
    {
        // Using translation key for potential localization
        return __('auto-alt-text::messages.generate_alt_text_action');
    }

    /**
     * The run method is primarily meant for bulk actions.
     * Individual field actions are often handled via HTTP endpoints triggered by JS.
     * This run method dispatches the job, mirroring the endpoint logic for consistency,
     * but might not be directly invoked by the default JS implementation in the spec.
     */
    public function run($items, $values): void
    {
        foreach ($items as $item) {
            $asset = Asset::find($item);
            if ($asset) {
                GenerateAltTextJob::dispatch($asset, $this->fieldHandle());
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

        // Only show for image assets in bulk context
        return str_starts_with($item->mimeType() ?? '', 'image/');
    }

    /**
     * Get the field handle from the context.
     */
    private function fieldHandle(): string
    {
        return $this->context['field']['handle'] ?? config('auto-alt-text.alt_text_field', 'alt');
    }

    // Optional: Define fields if the action needs configuration in bulk mode
    // public function blueprint() { ... }
}
