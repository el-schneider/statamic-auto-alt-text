<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
    $this->asset = $this->createTestAsset();
    $user = $this->createTestUser();
    $this->actingAs($user);
});

it('trigger endpoint responds under default cp route', function () {
    $this->post('/cp/auto-alt-text/generate', [
        'asset_path' => $this->asset->id(),
        'field' => 'alt',
    ])->assertOk()
        ->assertJson(['success' => true]);
});

it('check endpoint responds under default cp route', function () {
    $this->get('/cp/auto-alt-text/check?'.http_build_query([
        'asset_path' => $this->asset->id(),
        'field' => 'alt',
    ]))->assertOk()
        ->assertJsonStructure(['status']);
});

it('trigger endpoint rejects unauthenticated requests', function () {
    auth()->logout();

    $this->post('/cp/auto-alt-text/generate', [
        'asset_path' => $this->asset->id(),
        'field' => 'alt',
    ])->assertRedirect();
});
