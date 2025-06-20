<?php

declare(strict_types=1);

namespace ElSchneider\StatamicAutoAltText\Commands;

use ElSchneider\StatamicAutoAltText\Actions\GenerateAltText;
use ElSchneider\StatamicAutoAltText\Contracts\CaptionService;
use ElSchneider\StatamicAutoAltText\Jobs\GenerateAltTextJob;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Statamic\Assets\AssetCollection;
use Statamic\Console\RunsInPlease;
use Statamic\Contracts\Assets\Asset as AssetContract;
use Statamic\Facades\Asset;
use Statamic\Facades\AssetContainer;

final class GenerateAltTextCommand extends Command
{
    use RunsInPlease;

    protected $signature = 'statamic:auto-alt:generate
                            {container? : The asset container handle to process}
                            {--asset=* : Specific asset IDs or paths to process}
                            {--overwrite-existing : Overwrite existing alt text}
                            {--field= : The field to save alt text to (defaults to config)}
                            {--dispatch-jobs : Dispatch jobs instead of processing synchronously}';

    protected $description = 'Generate alt text for image assets';

    public function __construct(
        private readonly GenerateAltText $generateAltText,
        private readonly CaptionService $captionService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $containerHandle = $this->argument('container');
        $assetIdentifiers = $this->option('asset');
        $overwriteExisting = $this->option('overwrite-existing');
        $fieldName = $this->option('field') ?: config('statamic.auto-alt-text.alt_text_field', 'alt');
        $dispatchJobs = $this->option('dispatch-jobs');

        $assetsToProcess = $this->getAssetsToProcess($containerHandle, $assetIdentifiers, $overwriteExisting, $fieldName);

        if ($assetsToProcess->isEmpty()) {
            $this->info('No image assets found matching the criteria or needing alt text.');

            return self::SUCCESS;
        }

        $totalAssets = $assetsToProcess->count();
        $this->info("Found {$totalAssets} image assets to process.");

        if ($dispatchJobs) {
            // Dispatch jobs for parallel processing
            foreach ($assetsToProcess as $asset) {
                GenerateAltTextJob::dispatch($asset, $fieldName, false);
            }

            $this->info("Dispatched {$totalAssets} jobs for parallel processing.");
            $this->comment('Jobs have been queued. Use "php artisan queue:work" to process them.');

            return self::SUCCESS;
        }

        // Original synchronous processing
        $progressBar = $this->output->createProgressBar($totalAssets);
        $progressBar->start();

        $successCount = 0;
        $failCount = 0;

        foreach ($assetsToProcess as $asset) {
            try {
                $caption = $this->generateAltText->handle($asset, $fieldName);

                if ($caption !== null) {
                    $successCount++;
                } else {
                    // Caption generation failed or asset type was unsupported.
                    $failCount++;
                }
            } catch (Exception $e) {
                Log::error("Failed to generate alt text for asset {$asset->id()}: {$e->getMessage()}");
                $failCount++;
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        if ($failCount > 0) {
            $this->warn("Completed alt text generation: {$successCount} successful, {$failCount} failed. Check logs for details.");
        } else {
            $this->info("Completed alt text generation: {$successCount} successful.");
        }

        return self::SUCCESS;
    }

    private function getAssetsToProcess(?string $containerHandle, array $assetIdentifiers, bool $overwriteExisting, string $fieldName): AssetCollection
    {
        $assets = new AssetCollection;

        // If specific asset IDs or paths were provided
        if (! empty($assetIdentifiers)) {
            $foundAssets = new AssetCollection;
            foreach ($assetIdentifiers as $identifier) {
                // Try finding by ID first, then by path
                $asset = Asset::find($identifier) ?? Asset::findByPath($identifier);
                if ($asset) {
                    $foundAssets->push($asset);
                } else {
                    $this->warn("Could not find asset with identifier: {$identifier}");
                }
            }
            $assets = $foundAssets;
        }
        // If a container was specified
        elseif ($containerHandle) {
            $container = AssetContainer::findByHandle($containerHandle);
            if (! $container) {
                $this->error("Asset container '{$containerHandle}' not found.");

                return new AssetCollection;
            }
            $assets = Asset::query()->where('container', $container->handle())->get();
        }
        // If no specific assets or container specified, process all containers
        else {
            $allAssets = new AssetCollection;
            foreach (AssetContainer::all() as $container) {
                $containerAssets = Asset::query()->where('container', $container->handle())->get();
                $allAssets = $allAssets->merge($containerAssets);
            }
            $assets = $allAssets;
        }

        // Filter the collected assets
        return $assets->filter(function (AssetContract $asset) use ($overwriteExisting, $fieldName) {
            // Only process supported image assets based on the configured service
            if (! $this->captionService->supportsAssetType($asset)) {
                return false;
            }

            // Skip assets with existing alt text unless overwrite is enabled
            $currentAltText = $asset->get($fieldName);
            if (! $overwriteExisting && ! empty($currentAltText)) {
                return false;
            }

            return true;
        });
    }
}
