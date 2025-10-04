<?php

declare(strict_types=1);

use ElSchneider\StatamicAutoAltText\Jobs\GenerateAltTextJob;
use Illuminate\Support\Facades\Queue;

uses()->group('browser');

beforeEach(function () {
    $this->asset = $this->createTestAsset();
});

it('displays generate alt text action on asset editor', function () {
    Queue::fake();

    $url = "/cp/assets/browse/{$this->asset->containerHandle()}/";

    visit($url)
        ->click("[aria-label$='{$this->asset->basename()}']")
        ->click('Generate Alt Text')
        ->click('Run action');

    Queue::assertPushed(GenerateAltTextJob::class);
});

it('display field action on edit page', function () {
    Queue::fake();

    $url = "/cp/assets/browse/{$this->asset->containerHandle()}/{$this->asset->basename()}/edit";

    visit($url)
        ->click('.quick-list button')
        ->click('Generate Alt Text')
        ->assertSee('Generation started');

    Queue::assertPushed(GenerateAltTextJob::class);
});
