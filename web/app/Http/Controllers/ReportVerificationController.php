<?php

namespace App\Http\Controllers;

use App\Models\VerificationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * التحقق من أصالة تقرير صادر عن المنصة بالرقم المرجعي (يُطبع أيضًا كرمز QR في ملف PDF).
 * يعرض بيانات محدودة جدًا: لا اسم مستخدم ولا ملف ولا معاينة.
 */
class ReportVerificationController extends Controller
{
    public function form(?string $reference = null): View
    {
        $found = null;
        $reference = $reference ? strtoupper(trim($reference)) : null;

        if ($reference !== null) {
            $found = VerificationRequest::query()
                ->where('reference_number', $reference)
                ->whereIn('status', ['completed', 'needs_review'])
                ->with(['result', 'mediaFile'])
                ->first();
        }

        return view('reports.verify', ['reference' => $reference, 'found' => $found, 'searched' => $reference !== null]);
    }

    public function check(Request $request): RedirectResponse
    {
        $data = $request->validate(['reference' => ['required', 'string', 'max:30', 'regex:/^[A-Za-z0-9\-]+$/']]);

        return redirect()->route('reports.verify', strtoupper($data['reference']));
    }
}
