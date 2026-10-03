<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // سياسة كلمات المرور في كل المنصة
        Password::defaults(fn () => Password::min(8)->mixedCase()->numbers());

        Paginator::useBootstrapFive();

        $this->configureRateLimiting();
    }

    private function configureRateLimiting(): void
    {
        // محاولات الدخول: 5 كل دقيقة لكل (بريد + عنوان IP)
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)->by(
            strtolower((string) $request->input('email')).'|'.$request->ip()
        ));

        RateLimiter::for('register', fn (Request $request) => Limit::perHour(10)->by($request->ip()));

        RateLimiter::for('password-reset', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));

        // الرفع أكثر كلفة، فيُقيَّد لكل مستخدم
        RateLimiter::for('uploads', fn (Request $request) => Limit::perHour(30)->by((string) ($request->user()?->id ?: $request->ip())));

        RateLimiter::for('contact', fn (Request $request) => Limit::perHour(5)->by($request->ip()));

        // صفحات التقارير العامة: حماية من تخمين الرموز
        RateLimiter::for('public-report', fn (Request $request) => Limit::perMinute(30)->by($request->ip()));

        // رفع الزوار بلا تسجيل: أشد تقييدًا لأن لا حساب يُحاسَب، ولمنع استنزاف موارد خدمة الذكاء الاصطناعي
        RateLimiter::for('guest-uploads', fn (Request $request) => Limit::perHour(5)->by($request->ip()));

        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(60)->by((string) ($request->user()?->id ?: $request->ip())));
    }
}
