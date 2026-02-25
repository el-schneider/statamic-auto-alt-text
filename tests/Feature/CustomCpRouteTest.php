<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
    $this->asset = $this->createTestAsset();
    $this->loginUser();
});

it('trigger endpoint works with custom cp route', function () {
    config(['statamic.cp.route' => 'admin']);

    $this->post('/admin/auto-alt-text/generate', [
        'asset_path' => $this->asset->id(),
        'field' => 'alt',
    ])->assertOk()
        ->assertJson(['success' => true]);
});

it('check endpoint works with custom cp route', function () {
    config(['statamic.cp.route' => 'admin']);

    $this->get('/admin/auto-alt-text/check?'.http_build_query([
        'asset_path' => $this->asset->id(),
        'field' => 'alt',
    ]))->assertOk();
});
