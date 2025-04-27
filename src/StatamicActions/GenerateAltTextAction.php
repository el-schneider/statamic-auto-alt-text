<?php

declare(strict_types=1);

namespace ElSchneider\StatamicAutoAltText\StatamicActions;

use ElSchneider\StatamicAutoAltText\Actions\GenerateAltText;
use Statamic\Actions\Action;
use Statamic\Contracts\Assets\Asset;
use Statamic\Facades\User;

final class GenerateAltTextAction extends Action
{
    public static function title()
    {
        return __('Generate Alt Text');
    }

    public function visibleTo($item)
    {
        return $item instanceof Asset;
    }

    public function authorize($user, $item)
    {
        // Ensure the user has permission to update the asset
        return $user->can('update', $item);
    }

    public function run($items, $values)
    {
        $generateAltTextAction = app(GenerateAltText::class);
        $count = $items->count();

        if ($count === 0) {
            return __('No items selected.');
        }

        if ($count === 1) {
            $asset = $items->first();
            $caption = $generateAltTextAction->handle($asset);

            return $caption
                ? __('Alt text generated successfully for 1 item.')
                : __('Failed to generate alt text for the item.');
        }
        // Convert the Statamic Collection to an array of Assets for handleBatch
        $assetsArray = $items->all();
        $results = $generateAltTextAction->handleBatch($assetsArray);

        $successCount = count(array_filter($results)); // Count non-null results
        $failCount = $count - $successCount;

        if ($failCount === 0) {
            return trans_choice('Alt text generated successfully for :count items.', $successCount);
        }
        if ($successCount === 0) {
            return trans_choice('Failed to generate alt text for :count items.', $failCount);
        }

        return __('Generated alt text for :success items, failed for :fail items.', ['success' => $successCount, 'fail' => $failCount]);

    }
}
