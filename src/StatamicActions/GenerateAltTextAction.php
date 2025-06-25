<?php

declare(strict_types=1);

namespace ElSchneider\StatamicAutoAltText\StatamicActions;

use ElSchneider\StatamicAutoAltText\Contracts\CaptionService;
use ElSchneider\StatamicAutoAltText\Services\AssetExclusionService;
use ElSchneider\StatamicAutoAltText\StatamicAutoAltText;
use Statamic\Actions\Action;
use Statamic\Contracts\Assets\Asset as AssetContract;
use Statamic\Facades\Asset;

final class GenerateAltTextAction extends Action
{
    public function __construct(
        private readonly CaptionService $captionService,
        private readonly StatamicAutoAltText $autoAltText,
        private readonly AssetExclusionService $exclusionService
    ) {
        parent::__construct();
    }

    public static function title(): string
    {
        return __('auto-alt-text::messages.generate_alt_text_action');
    }

    public function run($items, $values): void
    {

        foreach ($items as $item) {
            $asset = Asset::find($item);

            if ($asset) {
                $this->autoAltText->dispatchGenerationJob($asset);
            }
        }
    }

    public function authorize($user, $item): bool
    {
        return $user->can('edit', $item);
    }

    public function visibleTo($item): bool
    {
        if (! $item instanceof AssetContract) {
            return false;
        }

        // Only show for supported image assets based on the configured service
        if (! $this->captionService->supportsAssetType($item)) {
            return false;
        }

        // Hide action for excluded assets
        if ($this->exclusionService->shouldExclude($item)) {
            return false;
        }

        return true;
    }
}
