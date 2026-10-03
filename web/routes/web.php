<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GuestVerificationController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicReportController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ReportVerificationController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\ShareController;
use App\Http\Controllers\VerificationController;
use Illuminate\Support\Facades\Route;

/*
| الصفحات العامة (زوار)
*/
Route::get('/', [PageController::class, 'home'])->name('home');
Route::get('/lang/{locale}', [LocaleController::class, 'switch'])->name('locale.switch');

Route::name('pages.')->group(function () {
    Route::get('/about', [PageController::class, 'about'])->name('about');
    Route::get('/how-it-works', [PageController::class, 'howItWorks'])->name('how');
    Route::get('/supported-content', [PageController::class, 'supported'])->name('supported');
    Route::get('/awareness', [PageController::class, 'awareness'])->name('awareness');
    Route::get('/faq', [PageController::class, 'faq'])->name('faq');
    Route::get('/privacy', [PageController::class, 'privacy'])->name('privacy');
    Route::get('/terms', [PageController::class, 'terms'])->name('terms');
    Route::get('/contact', [PageController::class, 'contact'])->name('contact');
    Route::post('/contact', [PageController::class, 'sendContact'])->middleware('throttle:contact')->name('contact.send');
});

// التقارير العامة المشتركة والتحقق من التقارير بالرقم المرجعي
Route::middleware('throttle:public-report')->group(function () {
    Route::get('/r/{token}', [PublicReportController::class, 'show'])->name('reports.public');
    Route::get('/r/{token}/media', [PublicReportController::class, 'media'])->name('reports.public.media');
    Route::get('/verify-report/{reference?}', [ReportVerificationController::class, 'form'])->name('reports.verify');
    Route::post('/verify-report', [ReportVerificationController::class, 'check'])->name('reports.verify.check');
});

// تجربة الزائر بلا حساب: رفع ملف واحد ورؤية نتيجته فقط؛ تنزيل التقرير ومشاركته يتطلبان حسابًا
Route::prefix('guest')->name('guest.')->middleware(['guest', 'throttle:public-report'])->group(function () {
    Route::post('/verify', [GuestVerificationController::class, 'store'])->middleware('throttle:guest-uploads')->name('verify');
    Route::get('/verifications/{verification}/progress', [GuestVerificationController::class, 'progress'])->name('progress');
    Route::get('/verifications/{verification}/status', [GuestVerificationController::class, 'status'])->name('status');
    Route::get('/verifications/{verification}', [GuestVerificationController::class, 'show'])->name('show');
    Route::get('/verifications/{verification}/media', [GuestVerificationController::class, 'media'])->name('media');
    Route::get('/verifications/{verification}/frames/{indicator}', [GuestVerificationController::class, 'frame'])->name('frame');
    // «طباعة التقرير» للزائر: يحفظ وجهة العودة ثم يحوّله إلى الدخول أو التسجيل
    Route::get('/verifications/{verification}/report-access/{via}', [GuestVerificationController::class, 'reportAccess'])
        ->whereIn('via', ['login', 'register'])->name('report.access');
});

// بعد الدخول/التسجيل: نقل نتيجة الزائر (المرتبطة بجلسة هذا المتصفح) إلى حسابه ثم فتح تقرير الطباعة
Route::get('/guest/verifications/{verification}/claim', [GuestVerificationController::class, 'claim'])
    ->middleware(['auth', 'active', 'throttle:public-report'])->name('guest.claim');

/*
| الزوار فقط
*/
Route::middleware('guest')->group(function () {
    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])->middleware('throttle:register');

    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:login');

    Route::get('/forgot-password', [PasswordResetController::class, 'requestForm'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'sendLink'])
        ->middleware('throttle:password-reset')->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'resetForm'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'reset'])
        ->middleware('throttle:password-reset')->name('password.update');
});

/*
| المستخدم المسجَّل (حساب نشط)
*/
Route::middleware(['auth', 'active'])->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');

    Route::prefix('verifications')->name('verifications.')->group(function () {
        Route::get('/', [VerificationController::class, 'index'])->name('index');
        Route::get('/new', [VerificationController::class, 'create'])->name('create');
        Route::post('/', [VerificationController::class, 'store'])->middleware('throttle:uploads')->name('store');
        Route::get('/{verification}', [VerificationController::class, 'show'])->name('show');
        Route::get('/{verification}/progress', [VerificationController::class, 'progress'])->name('progress');
        Route::get('/{verification}/status', [VerificationController::class, 'status'])->name('status');
        Route::post('/{verification}/retry', [VerificationController::class, 'retry'])->name('retry');
        Route::delete('/{verification}', [VerificationController::class, 'destroy'])->name('destroy');
        Route::get('/{verification}/media', [VerificationController::class, 'media'])->name('media');
        Route::get('/{verification}/print', [ReportController::class, 'print'])->name('print');
        Route::get('/{verification}/frames/{indicator}', [VerificationController::class, 'frame'])->name('frame');
        Route::post('/{verification}/share', [ShareController::class, 'store'])->name('share.store');
        Route::delete('/{verification}/share', [ShareController::class, 'destroy'])->name('share.destroy');
    });

    Route::get('/reports/{verification}/pdf', [ReportController::class, 'pdf'])->name('reports.pdf');

    Route::prefix('notifications')->name('notifications.')->group(function () {
        Route::get('/', [NotificationController::class, 'index'])->name('index');
        Route::post('/read-all', [NotificationController::class, 'readAll'])->name('read-all');
        Route::get('/{notification}', [NotificationController::class, 'open'])->name('open');
    });

    Route::get('/settings', [SettingsController::class, 'edit'])->name('settings.edit');
    Route::put('/settings/password', [SettingsController::class, 'updatePassword'])->name('settings.password');
    Route::delete('/settings/data', [SettingsController::class, 'deleteData'])->name('settings.data.delete');
});

/*
| مدير النظام
*/
Route::middleware(['auth', 'active', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [Admin\DashboardController::class, 'index'])->name('dashboard');

    Route::get('/users', [Admin\UserController::class, 'index'])->name('users.index');
    Route::get('/users/{user}', [Admin\UserController::class, 'show'])->name('users.show')->whereNumber('user');
    Route::put('/users/{user}/status', [Admin\UserController::class, 'updateStatus'])->name('users.status')->whereNumber('user');
    Route::put('/users/{user}/role', [Admin\UserController::class, 'updateRole'])->name('users.role')->whereNumber('user');
    Route::delete('/users/{user}', [Admin\UserController::class, 'destroy'])->name('users.destroy')->whereNumber('user');
    Route::post('/users/{id}/restore', [Admin\UserController::class, 'restore'])->name('users.restore')->whereNumber('id');

    Route::get('/verifications', [Admin\VerificationController::class, 'index'])->name('verifications.index');

    Route::get('/statistics', [Admin\StatisticsController::class, 'index'])->name('statistics');
    Route::get('/statistics/export', [Admin\StatisticsController::class, 'export'])->name('statistics.export');

    Route::get('/models', [Admin\ModelController::class, 'index'])->name('models.index');
    Route::post('/models/refresh', [Admin\ModelController::class, 'refresh'])->name('models.refresh');
    Route::put('/models/{model}', [Admin\ModelController::class, 'update'])->name('models.update');
    Route::post('/models/{model}/toggle', [Admin\ModelController::class, 'toggle'])->name('models.toggle');
    Route::post('/models/{model}/metrics', [Admin\ModelController::class, 'importMetrics'])->name('models.metrics');

    Route::get('/content', [Admin\ContentController::class, 'index'])->name('content.index');
    Route::put('/content/{section}', [Admin\ContentController::class, 'update'])->name('content.update');

    Route::get('/settings', [Admin\SystemSettingController::class, 'edit'])->name('settings.edit');
    Route::put('/settings', [Admin\SystemSettingController::class, 'update'])->name('settings.update');
    Route::post('/settings/purge', [Admin\SystemSettingController::class, 'purge'])->name('settings.purge');

    Route::get('/activity', [Admin\ActivityController::class, 'index'])->name('activity.index');
});
