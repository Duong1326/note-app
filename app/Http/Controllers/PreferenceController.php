<?php

namespace App\Http\Controllers;

use App\Http\Requests\Preference\UpdatePreferenceRequest;
use App\Services\PreferenceService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class PreferenceController extends Controller
{
    public function __construct(private PreferenceService $preferenceService)
    {
    }

    public function update(UpdatePreferenceRequest $request): JsonResponse|RedirectResponse
    {
        try {
            $preference = $this->preferenceService->upsert($request->user(), $request->validated());

            if ($request->expectsJson()) {
                return response()->json(['success' => true, 'preferences' => $preference]);
            }

            return back()->with('success', 'Đã lưu tùy chọn.');

        } catch (Exception $e) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
            }

            return back()->with('error', $e->getMessage());
        }
    }
}