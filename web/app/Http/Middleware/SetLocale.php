<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * يحدد لغة الواجهة: تفضيل المستخدم المسجل، ثم اللغة المحفوظة في الجلسة، ثم الافتراضية.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $supported = array_keys(config('aimv.locales'));

        $locale = $request->user()?->locale
            ?? $request->session()->get('locale')
            ?? config('app.locale');

        if (! in_array($locale, $supported, true)) {
            $locale = config('app.locale');
        }

        app()->setLocale($locale);
        Carbon::setLocale($locale);

        return $next($request);
    }
}
