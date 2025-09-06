<?php

declare(strict_types=1);

namespace ElSchneider\StatamicAutoAltText\Http\Controllers;

use ElSchneider\StatamicAutoAltText\Jobs\GenerateAltTextJob;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
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

        if (! $user || ! $user->can('edit', $asset)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 403);
        }

        $field = $validated['field'] ?? config('statamic.auto-alt-text.alt_text_field', 'alt');

        try {
            $currentValue = $asset->get($field);
            $cacheKey = "alt_text_job_{$asset->id()}_{$field}";
            $valueHash = md5($currentValue ?? '');

            Cache::put($cacheKey, $valueHash, now()->addMinutes(5));

            GenerateAltTextJob::dispatch($asset, $field);

            return response()->json([
                'success' => true,
                'message' => __('auto-alt-text::messages.generation_queued'),
            ]);

        } catch (Exception $e) {
            Log::error("Unexpected CP Alt Text Job Dispatch Error: {$e->getMessage()}", [
                'asset_id' => $asset->id(),
                'field' => $field,
                'exception' => $e,
            ]);

            return response()->json([
                'success' => false,
                'message' => __('auto-alt-text::messages.generation_queue_failed'),
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

        $altText = $asset->get($validated['field']);

        if (! empty($altText) && is_string($altText)) {
            $cacheKey = "alt_text_job_{$asset->id()}_{$validated['field']}";
            $originalHash = Cache::get($cacheKey);

            if ($originalHash === null) {
                return response()->json([
                    'status' => 'ready',
                    'caption' => $altText,
                ]);
            }

            $currentHash = md5($altText);

            if ($currentHash !== $originalHash) {
                Cache::forget($cacheKey);

                return response()->json([
                    'status' => 'ready',
                    'caption' => $altText,
                ]);
            }

            return response()->json(['status' => 'pending']);
        }

        return response()->json(['status' => 'pending']);

    }
}
