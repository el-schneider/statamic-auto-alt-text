<?php

declare(strict_types=1);

use ElSchneider\StatamicAutoAltText\Jobs\GenerateAltTextJob;
use ElSchneider\StatamicAutoAltText\Listeners\HandleAssetEvent;
use Illuminate\Support\Facades\Queue;
use Statamic\Assets\Asset;
use Statamic\Events\AssetSaving;

it('dispatches job when asset is saved without alt text', function () {
    Queue::fake();

    $asset = Mockery::mock(Asset::class);
    $asset->shouldReceive('get')
        ->with('alt')
        ->andReturn('');
    $asset->shouldReceive('get')
        ->with('auto_alt_text_ignore')
        ->andReturn(false);
    $asset->shouldReceive('containerHandle')
        ->andReturn('test');
    $asset->shouldReceive('path')
        ->andReturn('test-image.jpg');

    $event = new AssetSaving($asset);

    $listener = app(HandleAssetEvent::class);
    $listener->handle($event);

    Queue::assertPushed(GenerateAltTextJob::class);
});

it('skips assets with existing alt text', function () {
    Queue::fake();

    $asset = Mockery::mock(Asset::class);
    $asset->shouldReceive('get')
        ->with('alt')
        ->andReturn('Existing alt text');

    $event = new AssetSaving($asset);

    $listener = app(HandleAssetEvent::class);
    $listener->handle($event);

    Queue::assertNotPushed(GenerateAltTextJob::class);
});

it('skips events not configured for automatic generation', function () {
    Queue::fake();

    config(['statamic.auto-alt-text.automatic_generation_events' => []]);

    $asset = Mockery::mock(Asset::class);
    $asset->shouldReceive('get')
        ->with('alt')
        ->andReturn('');

    $event = new AssetSaving($asset);

    $listener = app(HandleAssetEvent::class);
    $listener->handle($event);

    Queue::assertNotPushed(GenerateAltTextJob::class);
});
