<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\SystemSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** إدارة المحتوى التوعوي والأسئلة الشائعة (تُخزَّن كـ JSON ثنائي اللغة في system_settings). */
class ContentController extends Controller
{
    private const KEYS = ['faq' => 'faq_items', 'awareness' => 'awareness_items'];

    public function index(): View
    {
        return view('admin.content', [
            'faq' => SystemSetting::get('faq_items', []) ?: [],
            'awareness' => SystemSetting::get('awareness_items', []) ?: [],
        ]);
    }

    public function update(Request $request, string $section): RedirectResponse
    {
        abort_unless(isset(self::KEYS[$section]), 404);

        $data = $request->validate([
            'items' => ['nullable', 'array', 'max:50'],
            'items.*.title.ar' => ['nullable', 'string', 'max:200'],
            'items.*.title.en' => ['nullable', 'string', 'max:200'],
            'items.*.body.ar' => ['nullable', 'string', 'max:5000'],
            'items.*.body.en' => ['nullable', 'string', 'max:5000'],
        ]);

        // نحذف الصفوف الفارغة، ويُعرض النص كنص عادي (Blade يهرّب المخرجات) فلا يُنفَّذ أي HTML
        $items = collect($data['items'] ?? [])
            ->map(fn ($item) => [
                'title' => ['ar' => trim((string) ($item['title']['ar'] ?? '')), 'en' => trim((string) ($item['title']['en'] ?? ''))],
                'body' => ['ar' => trim((string) ($item['body']['ar'] ?? '')), 'en' => trim((string) ($item['body']['en'] ?? ''))],
            ])
            ->filter(fn ($item) => $item['title']['ar'] !== '' || $item['title']['en'] !== '')
            ->values()->all();

        SystemSetting::put(self::KEYS[$section], $items, 'json', 'content');
        ActivityLog::record('admin.content_updated', 'تحديث المحتوى', properties: ['section' => $section, 'count' => count($items)]);

        return redirect()->to(route('admin.content.index').'#'.$section)->with('success', __('admin.content.saved'));
    }
}
