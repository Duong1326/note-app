<?php

namespace App\Http\Controllers;

use App\Http\Requests\Share\StoreShareRequest;
use App\Http\Requests\Share\UpdateShareRequest;
use App\Models\Note;
use App\Models\Share;
use App\Services\NoteService;
use App\Services\ShareService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ShareController extends Controller
{
    public function __construct(
        private ShareService $shareService,
        private NoteService $noteService,
    ) {
    }

    public function index(Request $request): View
    {
        try {
            $notes = $this->noteService->listSharedWithUser($request->user());

            return view('shares.index', compact('notes'));

        } catch (Exception $e) {
            return view('shares.index', ['notes' => collect()])
                ->with('error', $e->getMessage());
        }
    }

    public function store(StoreShareRequest $request, Note $note): JsonResponse|RedirectResponse
    {
        $this->assertOwner($note, $request);

        try {
            $share = $this->shareService->share(
                $note,
                $request->user(),
                $request->validated('email'),
                $request->validated('permission'),
            );

            if ($request->expectsJson()) {
                return response()->json(['success' => true, 'share' => $share->load('recipient')], 201);
            }

            return back()->with('success', 'Đã chia sẻ ghi chú thành công.');

        } catch (ValidationException $e) {
            throw $e;
        } catch (Exception $e) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
            }

            return back()->with('error', $e->getMessage());
        }
    }

    public function show(Request $request, Note $note): JsonResponse|View
    {
        $this->assertOwner($note, $request);

        try {
            $shares = $this->shareService->listForNote($note);

            if ($request->expectsJson()) {
                return response()->json(['success' => true, 'shares' => $shares]);
            }

            return view('shares.show', compact('note', 'shares'));

        } catch (Exception $e) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
            }

            return back()->with('error', $e->getMessage());
        }
    }

    public function update(UpdateShareRequest $request, Note $note, Share $share): JsonResponse|RedirectResponse
    {
        $this->assertOwner($note, $request);

        try {
            $share = $this->shareService->updatePermission($share, $request->validated('permission'));

            if ($request->expectsJson()) {
                return response()->json(['success' => true, 'share' => $share]);
            }

            return back()->with('success', 'Đã cập nhật quyền chia sẻ.');

        } catch (Exception $e) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
            }

            return back()->with('error', $e->getMessage());
        }
    }

    public function destroy(Request $request, Note $note, Share $share): JsonResponse|RedirectResponse
    {
        $this->assertOwner($note, $request);

        try {
            $this->shareService->revoke($share);

            if ($request->expectsJson()) {
                return response()->json(['success' => true]);
            }

            return back()->with('success', 'Đã thu hồi chia sẻ.');

        } catch (Exception $e) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
            }

            return back()->with('error', $e->getMessage());
        }
    }

    private function assertOwner(Note $note, Request $request): void
    {
        if ($note->user_id !== $request->user()->id) {
            abort(403, 'Bạn không có quyền quản lý chia sẻ ghi chú này.');
        }
    }
}
