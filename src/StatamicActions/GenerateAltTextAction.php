<?php

declare(strict_types=1);

namespace ElSchneider\StatamicAutoAltText\StatamicActions;

use ElSchneider\StatamicAutoAltText\StatamicAutoAltText;
use Statamic\Actions\Action;
use Statamic\Contracts\Assets\Asset;
use Statamic\Facades\User;

final class GenerateAltTextAction extends Action
{
    private StatamicAutoAltText $autoAltText; // Inject main addon class

    // Inject the service via constructor
    public function __construct(StatamicAutoAltText $autoAltText)
    {
        $this->autoAltText = $autoAltText;
        parent::__construct(); // Call parent constructor if extending a class that has one
    }

    public static function title()
    {
        return __('Generate Alt Text (Queued)'); // Indicate queuing
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

    /**
     * Run the action by dispatching jobs for each item.
     */
    public function run($items, $values)
    {
        $count = $items->count();

        if ($count === 0) {
            return __('No items selected.');
        }

        foreach ($items as $item) {
            if ($item instanceof Asset) {
                $this->autoAltText->dispatchGenerationJob($item);
            }
        }

        return trans_choice('Queued alt text generation for :count item(s).', $count);
    }
}
