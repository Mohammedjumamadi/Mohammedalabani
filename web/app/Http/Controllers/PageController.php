<?php

namespace App\Http\Controllers;

use App\Enums\MediaType;
use App\Mail\ContactMessage;
use App\Models\ActivityLog;
use App\Models\ModelVersion;
use App\Models\SystemSetting;
use App\Services\Ai\AiClient;
use App\Services\Media\FileValidator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class PageController extends Controller
{
    public function home(FileValidator $validator, AiClient $ai): View
    {
        $guestEnabled = auth()->guest() && config('aimv.guest.enabled');

        return view('pages.home', $guestEnabled
            ? GuestVerificationController::uploaderContext($validator, $ai)
            : ['guestTypes' => null]);
    }

    public function about(): View
    {
        // مقاييس الأداء تُعرض فقط إن وُجدت نتائج تقييم حقيقية مسجلة، ولا تُكتب أي نسبة ثابتة
        $models = ModelVersion::query()->whereNotNull('metrics')->orderBy('media_type')->get()->filter->hasMetrics();

        return view('pages.about', ['models' => $models]);
    }

    public function howItWorks(): View
    {
        return view('pages.how');
    }

    public function supported(FileValidator $validator): View
    {
        $types = [];
        foreach (MediaType::cases() as $type) {
            $types[$type->value] = $validator->allowedExtensions($type);
        }

        return view('pages.supported', ['types' => $types, 'maxLabel' => $validator->formatBytes($validator->maxBytes())]);
    }

    public function awareness(): View
    {
        return view('pages.awareness', ['articles' => $this->localizedItems('awareness_items')]);
    }

    public function faq(): View
    {
        return view('pages.faq', ['items' => $this->localizedItems('faq_items')]);
    }

    public function privacy(): View
    {
        return view('pages.static', ['page' => 'privacy']);
    }

    public function terms(): View
    {
        return view('pages.static', ['page' => 'terms']);
    }

    public function contact(): View
    {
        return view('pages.contact');
    }

    public function sendContact(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:190'],
            'subject' => ['required', 'string', 'max:150'],
            'message' => ['required', 'string', 'min:10', 'max:3000'],
        ]);

        try {
            Mail::to(SystemSetting::get('contact_email', 'support@aimediaverifier.test'))
                ->send(new ContactMessage($data['name'], $data['email'], $data['subject'], $data['message']));
        } catch (\Throwable $e) {
            Log::error('Contact mail failed', ['error' => $e->getMessage()]);

            return back()->withInput()->with('error', __('pages.contact.failed'));
        }

        ActivityLog::record('contact.sent', 'رسالة تواصل جديدة', properties: ['subject' => $data['subject']]);

        return back()->with('success', __('pages.contact.sent'));
    }

    /**
     * عناصر المحتوى (أسئلة/مقالات) التي يديرها المدير، بلغة الواجهة الحالية مع الرجوع للغة الأخرى.
     *
     * @return list<array{title: string, body: string}>
     */
    private function localizedItems(string $key): array
    {
        $locale = app()->getLocale();
        $fallback = $locale === 'ar' ? 'en' : 'ar';

        return collect(SystemSetting::get($key, []) ?: [])
            ->map(fn ($item) => [
                'title' => $item['title'][$locale] ?? $item['title'][$fallback] ?? '',
                'body' => $item['body'][$locale] ?? $item['body'][$fallback] ?? '',
            ])
            ->filter(fn ($item) => $item['title'] !== '')
            ->values()
            ->all();
    }
}
