<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Role;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class RegisterController extends Controller
{
    public function create(): View
    {
        abort_unless(SystemSetting::get('registration_open', true), 403, __('auth.register.closed'));

        return view('auth.register');
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(SystemSetting::get('registration_open', true), 403, __('auth.register.closed'));

        // توحيد البريد قبل فحص التفرّد حتى لا يتجاوز Ahmed@x.com حساب ahmed@x.com
        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'email:rfc', 'max:190', 'unique:users,email'],
            // الخطوة 2 من معالج التسجيل
            'phone' => ['required', 'string', 'min:7', 'max:30', 'regex:/^[0-9+\-\s()]+$/'],
            'city' => ['required', 'string', 'max:100'],
            // الخطوة 3
            'password' => ['required', 'confirmed', Password::defaults()],
            'account_type' => ['nullable', Rule::in(config('aimv.account_types'))],
            'organization' => ['nullable', 'string', 'max:150'],
            'terms' => ['accepted'],
        ]);

        $user = new User([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => trim($data['phone']),
            'city' => $data['city'],
            'password' => $data['password'],
            'account_type' => $data['account_type'] ?? 'general',
            'organization' => $data['organization'] ?? null,
            'locale' => app()->getLocale(),
        ]);
        // الدور والحالة يُحدَّدان من الخادم فقط ولا يقبلان أي مدخل من المستخدم
        $user->role_id = Role::idFor(Role::USER);
        $user->status = \App\Enums\UserStatus::Active;
        $user->save();

        event(new Registered($user));

        Auth::login($user);
        $request->session()->regenerate();

        ActivityLog::record('auth.register', 'إنشاء حساب جديد', $user, user: $user);

        // intended: يعيد من جاء من «طباعة التقرير» كزائر إلى نتيجته نفسها
        return redirect()->intended(route('dashboard'))->with('success', __('auth.register.welcome'));
    }
}
