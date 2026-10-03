<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);

        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            ActivityLog::record('auth.login_failed', 'محاولة دخول فاشلة', properties: ['email' => $credentials['email']]);

            // رسالة موحّدة: لا نكشف هل البريد مسجل أم لا
            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        $user = Auth::user();

        if (! $user->isActive()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            ActivityLog::record('auth.login_blocked', 'محاولة دخول لحساب معلّق', $user, user: $user);

            throw ValidationException::withMessages(['email' => __('auth.suspended')]);
        }

        // تجديد معرّف الجلسة يمنع هجمات تثبيت الجلسة
        $request->session()->regenerate();

        // اللغة التي اختارها الزائر قبل الدخول تبقى كما هي بدل أن تقفز إلى لغة الحساب المحفوظة
        $locale = $request->session()->get('locale');
        $user->forceFill(array_filter([
            'last_login_at' => now(),
            'locale' => array_key_exists((string) $locale, config('aimv.locales')) ? $locale : null,
        ]))->save();
        ActivityLog::record('auth.login', 'تسجيل دخول', $user, user: $user);

        return redirect()->intended($user->isAdmin() ? route('admin.dashboard') : route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        ActivityLog::record('auth.logout', 'تسجيل خروج', $request->user());

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', __('auth.logged_out'));
    }
}
