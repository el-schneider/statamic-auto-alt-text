<?php

declare(strict_types=1);

namespace ElSchneider\StatamicAutoAltText\Http\Controllers;

use ElSchneider\StatamicAutoAltText\Actions\GenerateAltText;
use ElSchneider\StatamicAutoAltText\Exceptions\CaptionGenerationException;
use ElSchneider\StatamicAutoAltText\Jobs\GenerateAltTextJob;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Statamic\Contracts\Assets\Asset as AssetContract;
use Statamic\Facades\Asset;

final class GenerateAltTextController extends Controller
{
    public function __invoke(Request $request, GenerateAltText $generateAltText): JsonResponse
    {
        $validated = $request->validate([
            'asset_path' => 'required|string',
            'field' => 'nullable|string',
            // 'context' => 'required|array', // Context validation might be needed later
        ]);

        /** @var ?AssetContract $asset */
        $asset = Asset::findById($validated['asset_path']);

        if (! $asset) {
            return response()->json([
                'success' => false,
                'message' => 'Asset not found by path: '.$validated['asset_path'],
            ], 404);
        }

        $user = Auth::user();
        // Use 'can' for authorization check
        if (! $user || ! $user->can('update', $asset)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 403);
        }

        $field = $validated['field'] ?? config('auto-alt-text.alt_text_field', 'alt');

        try {
            $caption = $generateAltText->handle($asset, $field);

            if ($caption) {
                return response()->json([
                    'success' => true,
                    'message' => __('statamic-auto-alt-text::messages.generation_success'),
                    'caption' => $caption,
                ]);
            }

            GenerateAltTextJob::dispatch($asset, $field);

            return response()->json([
                'success' => true,
                'message' => __('statamic-auto-alt-text::messages.generation_queued'),
            ]);

        } catch (CaptionGenerationException $e) {
            Log::error("CP Alt Text Generation Error: {$e->getMessage()}", ['asset_path' => $asset->path()]);
            GenerateAltTextJob::dispatch($asset, $field);

            return response()->json([
                'success' => true, // Still technically successful from user perspective (queued)
                'message' => __('statamic-auto-alt-text::messages.generation_queued_error'),
            ]);
        } catch (Exception $e) {
            Log::error("Unexpected CP Alt Text Generation Error: {$e->getMessage()}", ['asset_path' => $asset->path()]);
            GenerateAltTextJob::dispatch($asset, $field);

            return response()->json([
                'success' => true,
                'message' => __('statamic-auto-alt-text::messages.generation_queued_error'),
            ]);
        }
    }
}
