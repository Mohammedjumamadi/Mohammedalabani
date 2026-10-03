<?php

namespace App\Http\Controllers;

use App\Enums\Classification;
use App\Enums\MediaType;
use App\Models\MediaFile;
use App\Models\VerificationRequest;
use App\Support\DateBuckets;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $requests = fn () => VerificationRequest::query()->ownedBy($user);
        $byClass = fn (Classification $c) => $requests()->whereHas('result', fn ($q) => $q->where('classification', $c->value))->count();

        $byType = MediaFile::query()
            ->whereHas('verificationRequest', fn ($q) => $q->where('user_id', $user->id))
            ->selectRaw('media_type, count(*) as total')->groupBy('media_type')->pluck('total', 'media_type');

        return view('dashboard', [
            'user' => $user,
            'stats' => [
                'total' => $requests()->count(),
                'real' => $byClass(Classification::Real),
                'fake' => $byClass(Classification::Fake),
                'uncertain' => $byClass(Classification::Uncertain),
                'failed' => $requests()->where('status', 'failed')->count(),
            ],
            'recent' => $requests()->with(['mediaFile', 'result'])->latest()->limit(6)->get(),
            'typeChart' => [
                'labels' => collect(MediaType::cases())->map->label()->all(),
                'counts' => collect(MediaType::cases())->map(fn (MediaType $t) => (int) ($byType[$t->value] ?? 0))->all(),
            ],
            'monthly' => DateBuckets::monthly($requests()),
            'hasDemo' => $requests()->where('is_demo', true)->exists(),
        ]);
    }
}
