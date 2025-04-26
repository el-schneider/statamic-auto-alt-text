<?php

declare(strict_types=1);

use ElSchneider\StatamicAutoAltText\Actions\GenerateAltText;
use ElSchneider\StatamicAutoAltText\Exceptions\CaptionGenerationException;
use ElSchneider\StatamicAutoAltText\Jobs\GenerateAltTextJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Statamic\Facades\Asset;

// Route group for CP actions under the addon's namespace
// Prefix matches the Statamic::script handle used in the Service Provider
Route::name('statamic-auto-alt-text.')->prefix('statamic-auto-alt-text')->group(function () {

    Route::post('generate', function (Request $request) {
        $validated = $request->validate([
            'asset_id' => 'required|string',
            'field' => 'nullable|string',
            // Add validation for the context if needed, e.g., ensuring it's from an asset field
            // 'context' => 'required|array',
        ]);

        $asset = Asset::find($validated['asset_id']);

        if (! $asset) {
            return response()->json([
                'success' => false,
                'message' => 'Asset not found.',
            ], 404);
        }

        // Check if the user has permission to update this asset
        if (auth()->user()->cant('update', $asset)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 403);
        }

        $field = $validated['field'] ?? config('auto-alt-text.alt_text_field', 'alt');

        // Try to generate synchronously for a quick response in the CP,
        // but fall back to queueing if it fails or takes too long.
        try {
            // Note: You might want a shorter timeout here than the MoondreamService default
            // if synchronous generation is attempted.
            $caption = app(GenerateAltText::class)->handle($asset, $field);

            if ($caption) {
                return response()->json([
                    'success' => true,
                    'message' => __('statamic-auto-alt-text::messages.generation_success'),
                    'caption' => $caption, // Send the caption back to update the field immediately
                ]);
            }
            // If handle returns null (e.g., not an image, error handled within), queue it.
            GenerateAltTextJob::dispatch($asset, $field);

            return response()->json([
                'success' => true,
                'message' => __('statamic-auto-alt-text::messages.generation_queued'),
            ]);

        } catch (CaptionGenerationException $e) {
            // Log the specific error for debugging
            Log::error("CP Alt Text Generation Error: {$e->getMessage()}", ['asset' => $asset->id()]);
            // Queue the job for a background attempt
            GenerateAltTextJob::dispatch($asset, $field);

            // Inform the user it's been queued due to an error
            return response()->json([
                'success' => true, // Still technically successful from user perspective (queued)
                'message' => __('statamic-auto-alt-text::messages.generation_queued_error'),
            ]);
        } catch (Exception $e) {
            // Catch any other unexpected errors
            Log::error("Unexpected CP Alt Text Generation Error: {$e->getMessage()}", ['asset' => $asset->id()]);
            GenerateAltTextJob::dispatch($asset, $field);

            return response()->json([
                'success' => true,
                'message' => __('statamic-auto-alt-text::messages.generation_queued_error'),
            ]);
        }
    })->name('generate');

});
