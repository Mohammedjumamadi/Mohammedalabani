<?php

namespace App\Http\Controllers;

use App\Models\SharedReport;
use App\Models\VerificationRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PublicReportController extends Controller
{
    /** التقرير العام المشترك: يُعرض بالرمز السري فقط، وبلا بيانات المالك إذا اختار إخفاءها. */
    public function show(string $token): View
    {
        $shared = $this->resolve($token);

        $shared->increment('views_count');
        $verification = $shared->verificationRequest->load(['mediaFile', 'result.indicators', 'user']);
        abort_unless($verification->result, 404);

        return view('reports.public', [
            'shared' => $shared,
            'verification' => $verification,
            'result' => $verification->result,
            'media' => $verification->mediaFile,
            'token' => $token,
        ])->with('noindex', true);
    }

    public function media(string $token): BinaryFileResponse
    {
        $shared = $this->resolve($token);
        abort_unless($shared->show_preview, 404);

        $media = $shared->verificationRequest->mediaFile;
        abort_unless($media->existsOnDisk(), 404);

        $response = response()->file(Storage::disk($media->disk)->path($media->path), [
            'Content-Type' => $media->mime_type,
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ]);
        $response->setPrivate();
        $response->headers->addCacheControlDirective('no-store');

        return $response;
    }

    /** يعيد 404 موحّدة لكل الحالات (غير موجود/ملغى/منتهٍ) حتى لا يُكشف أي منها. */
    private function resolve(string $token): SharedReport
    {
        abort_unless(strlen($token) === 48 && ctype_alnum($token), 404);

        $shared = SharedReport::findActiveByToken($token);
        abort_unless($shared && $shared->verificationRequest && $shared->verificationRequest->status->hasResult(), 404);

        return $shared;
    }
}
