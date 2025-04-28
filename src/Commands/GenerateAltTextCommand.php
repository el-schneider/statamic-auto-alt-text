<?php

declare(strict_types=1);

namespace ElSchneider\StatamicAutoAltText\Commands;

use ElSchneider\StatamicAutoAltText\Actions\GenerateAltText;
use ElSchneider\StatamicAutoAltText\Contracts\CaptionService;
use Illuminate\Console\Command;
use Statamic\Assets\AssetCollection;
use Statamic\Contracts\Assets\Asset as AssetContract;
use Statamic\Facades\Asset;
use Statamic\Facades\AssetContainer;

final class GenerateAltTextCommand extends Command
{
    protected $signature = 'auto-alt:generate
                            {container? : The asset container handle to process}
                            {--asset=* : Specific asset IDs or paths to process}
                            {--overwrite-existing : Overwrite existing alt text}
                            {--batch=50 : Number of assets to process in each batch}
                            {--field= : The field to save alt text to (defaults to config)}';

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
        $batchSize = max(1, (int) $this->option('batch')); // Ensure batch size is at least 1
        $fieldName = $this->option('field') ?: config('statamic.auto-alt-text.alt_text_field', 'alt');

        $assetsToProcess = $this->getAssetsToProcess($containerHandle, $assetIdentifiers, $overwriteExisting, $fieldName);

        if ($assetsToProcess->isEmpty()) {
            $this->info('No image assets found matching the criteria or needing alt text.');

            return self::SUCCESS;
        }

        $totalAssets = $assetsToProcess->count();
        $this->info("Found {$totalAssets} image assets to process.");

        $progressBar = $this->output->createProgressBar($totalAssets);
        $progressBar->start();

        $batches = $assetsToProcess->chunk($batchSize);
        $successCount = 0;
        $failCount = 0;

        foreach ($batches as $batch) {
            // Pass batch items as an array to handleBatch
            $results = $this->generateAltText->handleBatch($batch->all(), $fieldName);

            foreach ($batch as $asset) {
                if (isset($results[$asset->id()]) && $results[$asset->id()] !== null) {
                    $successCount++;
                } else {
                    $failCount++;
                }
                $progressBar->advance();
            }
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
        $assets = new AssetCollection();

        // If specific asset IDs or paths were provided
        if (! empty($assetIdentifiers)) {
            $foundAssets = new AssetCollection();
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

                return new AssetCollection(); // Return empty collection
            }
            // Query assets within the specified container
            $assets = Asset::query()->where('container', $container->handle())->get();
        }
        // If no specific assets or container specified, process all containers
        else {
            $allAssets = new AssetCollection();
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
