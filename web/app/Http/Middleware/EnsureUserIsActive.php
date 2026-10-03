<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    /**
     * يُخرج المستخدم فورًا إذا عُلّق حسابه أثناء جلسة نشطة.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->isActive()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson()) {
                abort(403, __('auth.suspended'));
            }

            return redirect()->route('login')->withErrors(['email' => __('auth.suspended')]);
        }

        return $next($request);
    }
}
