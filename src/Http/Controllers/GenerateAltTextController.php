<?php

declare(strict_types=1);

namespace ElSchneider\StatamicAutoAltText\Http\Controllers;

use ElSchneider\StatamicAutoAltText\Jobs\GenerateAltTextJob;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Statamic\Contracts\Assets\Asset as AssetContract;
use Statamic\Contracts\Auth\User as UserContract;
use Statamic\Facades\Asset;

final class GenerateAltTextController extends Controller
{
    /**
     * Trigger the alt text generation job.
     */
    public function trigger(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'asset_path' => 'required|string',
            'field' => 'nullable|string',
        ]);

        /** @var ?AssetContract $asset */
        $asset = Asset::findById($validated['asset_path']);

        if (! $asset) {
            return response()->json([
                'success' => false,
                'message' => 'Asset not found: '.$validated['asset_path'],
            ], 404);
        }

        /** @var ?UserContract $user */
        $user = Auth::user();

        // Ensure user exists and can update the asset
        if (! $user || ! $user->can('edit', $asset)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 403);
        }

        $field = $validated['field'] ?? config('statamic.auto-alt-text.alt_text_field', 'alt');

        try {
            // Always dispatch the job
            GenerateAltTextJob::dispatch($asset, $field);

            return response()->json([
                'success' => true,
                'message' => __('auto-alt-text::messages.generation_queued'), // Use the existing queued message
            ]);

        } catch (Exception $e) {
            // Log unexpected errors during dispatching
            Log::error("Unexpected CP Alt Text Job Dispatch Error: {$e->getMessage()}", [
                'asset_id' => $asset->id(), // Use id() which is definitely available
                'field' => $field,
                'exception' => $e,
            ]);

            // Inform the user that queuing failed
            return response()->json([
                'success' => false, // Indicate failure to queue
                'message' => __('auto-alt-text::messages.generation_queue_failed'), // Consider adding a specific message
            ], 500);
        }
    }

    /**
     * Check if the alt text has been generated for an asset field.
     */
    public function check(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'asset_path' => 'required|string',
            'field' => 'required|string',
        ]);

        /** @var ?AssetContract $asset */
        $asset = Asset::findById($validated['asset_path']);

        if (! $asset) {
            return response()->json(['status' => 'not_found', 'message' => 'Asset not found.'], 404);
        }

        // No need to re-check permissions here, assume the initial trigger was authorized.
        // Fetch the *latest* data for the asset
        // $asset = $asset->fresh();
        $altText = $asset->get($validated['field']);

        if (! empty($altText) && is_string($altText)) {
            return response()->json([
                'status' => 'ready',
                'caption' => $altText,
            ]);
        }

        return response()->json(['status' => 'pending']);

    }
}
