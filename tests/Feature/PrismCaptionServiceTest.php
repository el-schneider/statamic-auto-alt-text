<?php

declare(strict_types=1);

use ElSchneider\StatamicAutoAltText\Contracts\CaptionService;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Testing\TextResponseFake;
use Prism\Prism\ValueObjects\Media\Image;

// Pins the Prism request chain used by PrismCaptionService. Prism ships breaking
// changes in 0.x minors, so the supported range in composer.json is only credible
// if this runs against every minor in it.
it('sends the image through Prism and returns the caption', function () {
    $fake = Prism::fake([
        TextResponseFake::make()->withText('A white square.'),
    ]);

    $asset = $this->createTestAsset();

    $caption = app(CaptionService::class)->generateCaption($asset);

    expect($caption)->toBe('A white square.');

    $fake->assertRequest(function (array $requests) {
        $request = collect($requests)->flatten()->sole();

        expect($request->provider())->toBe('openai')
            ->and($request->model())->toBe('gpt-4.1')
            ->and($request->maxTokens())->toBe(config('statamic.auto-alt-text.max_tokens'));

        $images = collect($request->messages())
            ->flatMap(fn ($message) => $message->images())
            ->filter(fn ($part) => $part instanceof Image);

        expect($images)->toHaveCount(1);
    });
});
