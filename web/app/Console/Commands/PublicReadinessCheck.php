<?php

namespace App\Console\Commands;

use App\Models\User;
use Database\Seeders\UserSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * يفحص هل يمكن نشر الموقع برابط عام (Cloudflare Tunnel) دون كشف أسرار أو حسابات معروفة.
 * يستدعيه share-aimv.ps1 قبل إنشاء الرابط، ويرفض النشر إن بقيت مشكلة.
 */
class PublicReadinessCheck extends Command
{
    protected $signature = 'aimv:public-check {--fix : أصلح ما يمكن إصلاحه تلقائيًا (وضع التصحيح، كلمات مرور الحسابات التجريبية)}';

    protected $description = 'يتحقق من جاهزية الموقع للنشر برابط عام';

    public function handle(): int
    {
        $problems = 0;

        if (config('app.debug')) {
            if ($this->option('fix') && $this->disableDebugInEnvFile()) {
                $this->info('✓ أُوقف وضع التصحيح (APP_DEBUG=false) في web/.env');
            } else {
                $this->error('✗ وضع التصحيح مفعّل (APP_DEBUG=true): صفحات الأخطاء تكشف الأسرار لأي زائر.');
                $problems++;
            }
        } else {
            $this->info('✓ وضع التصحيح متوقف');
        }

        if (! config('app.key')) {
            $this->error('✗ APP_KEY فارغ: شغّل php artisan key:generate');
            $problems++;
        }

        foreach ([UserSeeder::ADMIN_EMAIL => UserSeeder::ADMIN_PASSWORD, UserSeeder::USER_EMAIL => UserSeeder::USER_PASSWORD] as $email => $default) {
            $user = User::where('email', $email)->first();
            if (! $user || ! Hash::check($default, $user->password)) {
                continue;
            }
            if ($this->option('fix')) {
                $password = Str::password(16, symbols: false);
                $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
                $this->warn("! كلمة مرور {$email} كانت معروفة للجميع؛ الجديدة: {$password}   (احفظها الآن، لن تظهر مرة أخرى)");
            } else {
                $this->error("✗ الحساب {$email} ما زال بكلمة المرور التجريبية المنشورة في README.");
                $problems++;
            }
        }

        if ($problems > 0) {
            $this->line('شغّل الأمر مع --fix لإصلاح ذلك تلقائيًا.');

            return self::FAILURE;
        }

        $this->info('الموقع جاهز للنشر برابط عام.');

        return self::SUCCESS;
    }

    /** يبدّل APP_DEBUG=true إلى false في ملف .env مع نسخة احتياطية. يأخذ أثره عند تشغيل الموقع التالي. */
    private function disableDebugInEnvFile(): bool
    {
        $path = app()->environmentFilePath();
        if (! is_file($path) || ! is_writable($path)) {
            return false;
        }
        $content = (string) file_get_contents($path);
        $updated = preg_replace('/^APP_DEBUG\s*=.*$/m', 'APP_DEBUG=false', $content, -1, $count);
        if (! $count) {
            $updated = rtrim($content)."\nAPP_DEBUG=false\n";
        }
        copy($path, $path.'.before-public');
        file_put_contents($path, $updated);
        config(['app.debug' => false]);

        return true;
    }
}
