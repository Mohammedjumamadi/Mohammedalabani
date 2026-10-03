<?php

namespace App\Http\Controllers\Admin;

use App\Enums\MediaType;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\MediaFile;
use App\Models\SystemSetting;
use App\Services\Media\FileValidator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SystemSettingController extends Controller
{
    public function edit(FileValidator $validator): View
    {
        return view('admin.settings', [
            'settings' => [
                'threshold_fake' => (int) SystemSetting::get('threshold_fake'),
                'threshold_real' => (int) SystemSetting::get('threshold_real'),
                'max_upload_mb' => (int) SystemSetting::get('max_upload_mb'),
                'retention_days' => (int) SystemSetting::get('retention_days'),
                'registration_open' => (bool) SystemSetting::get('registration_open', true),
                'contact_email' => (string) SystemSetting::get('contact_email', ''),
            ],
            'extensions' => collect(MediaType::cases())->mapWithKeys(fn (MediaType $t) => [$t->value => $validator->allowedExtensions($t)])->all(),
            'supported' => collect(MediaType::cases())->mapWithKeys(fn (MediaType $t) => [$t->value => config("aimv.media.types.{$t->value}.extensions")])->all(),
            'serverLimit' => $validator->formatBytes($validator->serverLimitBytes()),
            'expiredCount' => MediaFile::query()->whereNotNull('expires_at')->where('expires_at', '<', now())->whereNull('file_deleted_at')->count(),
        ]);
    }

    /** تشغيل حذف الملفات المنتهية يدويًا بدل انتظار المجدول. */
    public function purge(): RedirectResponse
    {
        Artisan::call('media:purge-expired');

        return back()->with('success', trim(Artisan::output()));
    }

    public function update(Request $request): RedirectResponse
    {
        $supported = fn (string $type) => config("aimv.media.types.{$type}.extensions");

        $data = $request->validate([
            'threshold_fake' => ['required', 'integer', 'between:2,100'],
            'threshold_real' => ['required', 'integer', 'between:1,99', 'lt:threshold_fake'],
            'max_upload_mb' => ['required', 'integer', 'between:1,2048'],
            'retention_days' => ['required', 'integer', 'between:1,3650'],
            'registration_open' => ['nullable', 'boolean'],
            'contact_email' => ['required', 'email', 'max:190'],
            'extensions' => ['required', 'array'],
            'extensions.image' => ['required', 'array', 'min:1'],
            'extensions.image.*' => ['string', Rule::in($supported('image'))],
            'extensions.video' => ['required', 'array', 'min:1'],
            'extensions.video.*' => ['string', Rule::in($supported('video'))],
            'extensions.audio' => ['required', 'array', 'min:1'],
            'extensions.audio.*' => ['string', Rule::in($supported('audio'))],
        ]);

        $before = [
            'threshold_fake' => SystemSetting::get('threshold_fake'), 'threshold_real' => SystemSetting::get('threshold_real'),
            'max_upload_mb' => SystemSetting::get('max_upload_mb'), 'retention_days' => SystemSetting::get('retention_days'),
        ];

        SystemSetting::put('threshold_fake', (int) $data['threshold_fake'], 'int', 'classification');
        SystemSetting::put('threshold_real', (int) $data['threshold_real'], 'int', 'classification');
        SystemSetting::put('max_upload_mb', (int) $data['max_upload_mb'], 'int', 'uploads');
        SystemSetting::put('retention_days', (int) $data['retention_days'], 'int', 'uploads');
        SystemSetting::put('allowed_extensions', array_map('array_values', $data['extensions']), 'json', 'uploads');
        SystemSetting::put('registration_open', $request->boolean('registration_open'), 'bool', 'general');
        SystemSetting::put('contact_email', $data['contact_email'], 'string', 'general');

        ActivityLog::record('admin.settings_updated', 'تحديث إعدادات النظام', properties: [
            'before' => $before,
            'after' => array_intersect_key($data, $before),
        ]);

        return back()->with('success', __('admin.settings.saved'));
    }
}
