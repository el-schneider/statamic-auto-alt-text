<?php

declare(strict_types=1);

namespace ElSchneider\StatamicAutoAltText\Commands;

use ElSchneider\StatamicAutoAltText\Actions\GenerateAltText;
use ElSchneider\StatamicAutoAltText\Contracts\CaptionService;
use ElSchneider\StatamicAutoAltText\Jobs\GenerateAltTextJob;
use ElSchneider\StatamicAutoAltText\Services\AssetExclusionService;
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
                            {container? : Asset container handle (e.g. "assets"). If omitted, all containers are processed}
                            {--asset=* : Asset ID or path to process. Repeatable (e.g. --asset=assets::hero.jpg --asset=assets::logo.png)}
                            {--overwrite-existing : Regenerate alt text even if the field already has a value}
                            {--field= : The field to save alt text to (defaults to the "alt_text_field" config value)}
                            {--dispatch-jobs : Queue jobs for async processing instead of running synchronously}';

    protected $description = 'Generate alt text for image assets';

    protected $help = <<<'HELP'
        Process all image assets across all containers:
          <info>php please auto-alt:generate</info>

        Process only assets in a specific container:
          <info>php please auto-alt:generate assets</info>

        Process specific assets by ID (container::path):
          <info>php please auto-alt:generate --asset=assets::images/hero.jpg</info>
          <info>php please auto-alt:generate --asset=assets::images/hero.jpg --asset=assets::images/logo.png</info>

        Overwrite existing alt text:
          <info>php please auto-alt:generate assets --overwrite-existing</info>

        Dispatch as queued jobs (requires a running queue worker):
          <info>php please auto-alt:generate --dispatch-jobs</info>

        Assets are filtered automatically: non-image assets, excluded assets (via config patterns), and assets that already have alt text are skipped.
        HELP;

    public function __construct(
        private readonly GenerateAltText $generateAltText,
        private readonly CaptionService $captionService,
        private readonly AssetExclusionService $exclusionService
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

            // Check if asset should be excluded based on patterns
            if ($this->exclusionService->shouldExclude($asset)) {
                return false;
            }

            return true;
        });
    }
}
