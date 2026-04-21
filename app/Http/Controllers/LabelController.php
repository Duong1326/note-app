<?php

namespace App\Http\Controllers;

use App\Http\Requests\Label\StoreLabelRequest;
use App\Http\Requests\Label\UpdateLabelRequest;
use App\Models\Label;
use App\Services\LabelService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class LabelController extends Controller
{
    public function __construct(private LabelService $labelService)
    {
    }

    public function store(StoreLabelRequest $request): JsonResponse|RedirectResponse
    {
        try {
            $label = $this->labelService->create($request->user(), $request->validated('name'));

            if ($request->wantsJson()) {
                return response()->json([
                    'message' => 'Tạo nhãn thành công',
                    'data' => $label
                ], 201);
            }

            return redirect()->back()->with('success', 'Tạo nhãn thành công!');
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            if ($request->wantsJson()) {
                return response()->json(['message' => $e->getMessage()], 500);
            }
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function update(UpdateLabelRequest $request, Label $label): JsonResponse|RedirectResponse
    {
        try {
            $this->authorizeLabel($label, $request);
            $label = $this->labelService->rename($label, $request->validated('name'));

            if ($request->wantsJson()) {
                return response()->json([
                    'message' => 'Cập nhật nhãn thành công',
                    'data' => $label
                ], 200);
            }

            return redirect()->back()->with('success', 'Cập nhật nhãn thành công!');
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            if ($request->wantsJson()) {
                return response()->json(['message' => $e->getMessage()], 500);
            }
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function destroy(Label $label, Request $request): JsonResponse|RedirectResponse
    {
        try {
            $this->authorizeLabel($label, $request);
            $this->labelService->delete($label);

            if ($request->wantsJson()) {
                return response()->json([
                    'message' => 'Xóa nhãn thành công',
                ], 200);
            }

            return redirect()->back()->with('success', 'Xóa nhãn thành công!');
        } catch (\Exception $e) {
            if ($request->wantsJson()) {
                return response()->json(['message' => $e->getMessage()], 500);
            }
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /**
     * Ensure the authenticated user owns the label.
     */
    private function authorizeLabel(Label $label, Request $request): void
    {
        if ($label->user_id !== $request->user()->id) {
            abort(403, 'Bạn không có quyền thao tác trên nhãn này.');
        }
    }
}