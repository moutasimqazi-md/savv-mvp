<?php

namespace Savv\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Savv\Enums\ImportSessionStatus;
use Savv\Jobs\ConfirmImportSession;
use Savv\Models\ImportPreview;
use Savv\Models\ImportSession;
use Savv\Services\ImportSessionService;

class ImportSessionController extends Controller
{
    public function show(Request $request, ImportSession $importSession, ImportSessionService $service): View|JsonResponse
    {
        $this->authorize('view', $importSession);

        $importSession = $service->refreshStatus($importSession);

        if ($request->wantsJson()) {
            return response()->json($this->statusPayload($importSession));
        }

        return view('imports.show', ['importSession' => $importSession]);
    }

    public function browser(Request $request, ImportSession $importSession): View
    {
        $this->authorize('view', $importSession);

        $viewToken = null;

        if (in_array($importSession->status, [ImportSessionStatus::Starting, ImportSessionStatus::Ready, ImportSessionStatus::AwaitingLogin, ImportSessionStatus::ReadyToScan], true)) {
            $viewToken = $importSession->issueViewToken((int) config('savv.import_session.view_token_ttl_seconds', 60));
        }

        return view('imports.browser', [
            'importSession' => $importSession,
            'viewToken' => $viewToken,
            'streamEnabled' => (bool) config('savv.runner.stream_enabled'),
        ]);
    }

    public function scan(Request $request, ImportSession $importSession, ImportSessionService $service): RedirectResponse
    {
        $this->authorize('update', $importSession);

        try {
            $service->requestScan($importSession);
        } catch (\Throwable $e) {
            return back()->withErrors(['scan' => $e->getMessage()]);
        }

        return redirect()->route('imports.show', $importSession);
    }

    public function preview(Request $request, ImportSession $importSession): JsonResponse
    {
        $this->authorize('view', $importSession);

        $previews = $importSession->previews()->orderBy('id')->get();

        return response()->json([
            'status' => $importSession->status->value,
            'previews' => $previews->map(fn (ImportPreview $p) => [
                'public_id' => $p->public_id,
                'provider_order_id' => $p->provider_order_id,
                'selected' => $p->selected,
                'observed_at' => $p->observed_at?->toIso8601String(),
                'parser_version' => $p->parser_version,
                'order' => $p->normalized_payload,
                'warnings' => $p->field_warnings,
            ]),
        ]);
    }

    public function confirm(Request $request, ImportSession $importSession): RedirectResponse
    {
        $this->authorize('update', $importSession);

        $validated = $request->validate([
            'selected' => ['required', 'array', 'min:1'],
            'selected.*' => ['string'],
        ]);

        if ($importSession->status !== ImportSessionStatus::PreviewReady) {
            return back()->withErrors(['confirm' => 'This import session has no preview ready to confirm.']);
        }

        $selectedIds = ImportPreview::query()
            ->where('import_session_id', $importSession->id)
            ->whereIn('public_id', $validated['selected'])
            ->pluck('id')
            ->all();

        if (empty($selectedIds)) {
            return back()->withErrors(['confirm' => 'Select at least one order to import.']);
        }

        $importSession->forceFill(['status' => ImportSessionStatus::Importing])->save();

        ConfirmImportSession::dispatch($importSession->id, $selectedIds)->onQueue('imports');

        return redirect()->route('imports.show', $importSession);
    }

    public function cancel(Request $request, ImportSession $importSession, ImportSessionService $service): RedirectResponse
    {
        $this->authorize('update', $importSession);

        $service->cancel($importSession);

        return redirect()->route('connections.index')
            ->with('status', 'Import cancelled and the temporary browser was terminated.');
    }

    private function statusPayload(ImportSession $importSession): array
    {
        return [
            'public_id' => $importSession->public_id,
            'status' => $importSession->status->value,
            'safe_error_code' => $importSession->safe_error_code,
            'expires_at' => $importSession->expires_at->toIso8601String(),
        ];
    }
}
