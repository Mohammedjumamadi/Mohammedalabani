<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\ModelVersion;
use App\Services\Ai\AiClient;
use App\Services\Evaluation\MetricsImporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class ModelController extends Controller
{
    public function index(AiClient $ai): View
    {
        return view('admin.models.index', [
            'versions' => ModelVersion::query()->withCount('results')->orderBy('media_type')->orderByDesc('id')->get(),
            'health' => $ai->health(),
            'components' => $ai->models(),
        ]);
    }

    /** يحدّث حالة الخدمة وقائمة النماذج المفتوحة المصدر فورًا. */
    public function refresh(): RedirectResponse
    {
        Cache::forget('ai.health');
        Cache::forget('ai.models');

        return back()->with('success', __('admin.models.refreshed'));
    }

    public function toggle(ModelVersion $model): RedirectResponse
    {
        $model->forceFill(['is_active' => ! $model->is_active])->save();
        ActivityLog::record('admin.model_toggled', 'تغيير حالة نسخة نموذج', $model, ['active' => $model->is_active]);

        return back()->with('success', __('admin.models.toggled'));
    }

    public function update(Request $request, ModelVersion $model): RedirectResponse
    {
        $data = $request->validate([
            'description' => ['nullable', 'string', 'max:1000'],
            'source_url' => ['nullable', 'url', 'max:255'],
            'license' => ['nullable', 'string', 'max:100'],
        ]);
        $model->update($data);
        ActivityLog::record('admin.model_updated', 'تحديث بيانات نموذج', $model);

        return back()->with('success', __('admin.models.updated'));
    }

    public function importMetrics(Request $request, ModelVersion $model, MetricsImporter $importer): RedirectResponse
    {
        $request->validate(['metrics_file' => ['required', 'file', 'max:512', 'extensions:json']]);

        $decoded = json_decode((string) $request->file('metrics_file')->get(), true);
        if (! is_array($decoded)) {
            return back()->withErrors(['metrics_file' => __('admin.models.import_errors.json')]);
        }

        $metrics = $importer->import($model, $decoded);

        ActivityLog::record('admin.model_metrics_imported', 'استيراد مقاييس تقييم نموذج', $model, ['n' => $metrics['n_samples']]);

        return back()->with('success', __('admin.models.imported'));
    }
}
