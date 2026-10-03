<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Services\Verification\VerificationCleaner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function edit(Request $request): View
    {
        return view('settings.edit', ['user' => $request->user()]);
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::defaults(), 'different:current_password'],
        ]);

        $user = $request->user();
        $user->forceFill(['password' => $data['password']])->save();

        ActivityLog::record('auth.password_changed', 'تغيير كلمة المرور', $user);

        return back()->with('success', __('settings.password_changed'));
    }

    /** يحذف كل ملفات المستخدم ونتائج تحليلها وروابط مشاركتها. يتطلب تأكيد كلمة المرور. */
    public function deleteData(Request $request, VerificationCleaner $cleaner): RedirectResponse
    {
        $request->validate(['delete_password' => ['required', 'current_password']]);

        $user = $request->user();
        $count = 0;

        foreach ($user->verificationRequests()->with(['mediaFile', 'result'])->cursor() as $verification) {
            $cleaner->destroy($verification);
            $count++;
        }
        $user->userNotifications()->delete();

        ActivityLog::record('data.deleted', 'حذف كل ملفات المستخدم وبيانات تحليلها', $user, ['count' => $count]);

        return back()->with('success', __('settings.data_deleted', ['count' => $count]));
    }
}
