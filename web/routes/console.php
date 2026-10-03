<?php

use Illuminate\Support\Facades\Schedule;

// حذف الملفات التي انتهت مدة الاحتفاظ بها (تشغيل المجدول: php artisan schedule:work)
Schedule::command('media:purge-expired')->dailyAt('03:00');
