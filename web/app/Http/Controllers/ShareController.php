<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\SharedReport;
use App\Models\VerificationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ShareController extends Controller
{
    public function store(Request $request, VerificationRequest $verification): RedirectResponse
    {
        $this->authorize('share', $verification);

        $data = $request->validate([
            'hide_owner' => ['nullable', 'boolean'],
            'show_preview' => ['nullable', 'boolean'],
            'expires_in' => ['nullable', Rule::in(['7', '30', '90'])],
        ]);

        // رابط نشط واحد لكل تقرير: إنشاء رابط جديد يلغي السابق
        $verification->sharedReports()->whereNull('revoked_at')->update(['revoked_at' => now()]);

        $shared = SharedReport::issue(
            $verification,
            $request->user(),
            hideOwner: $request->boolean('hide_owner'),
            showPreview: $request->boolean('show_preview'),
            expiresAt: isset($data['expires_in']) ? now()->addDays((int) $data['expires_in']) : null,
        );

        // لا يُسجَّل الرمز نفسه في سجل النشاط
        ActivityLog::record('report.shared', 'إنشاء رابط مشاركة عام', $verification, ['shared_report_id' => $shared->id]);

        return redirect()->to(route('verifications.show', $verification).'#share')->with('success', __('verifications.share.created'));
    }

    public function destroy(VerificationRequest $verification): RedirectResponse
    {
        $this->authorize('share', $verification);

        $verification->sharedReports()->whereNull('revoked_at')->update(['revoked_at' => now()]);
        ActivityLog::record('report.unshared', 'إلغاء رابط المشاركة العام', $verification);

        return redirect()->to(route('verifications.show', $verification).'#share')->with('success', __('verifications.share.revoked'));
    }
}
