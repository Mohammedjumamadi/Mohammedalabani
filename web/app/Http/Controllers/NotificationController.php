<?php

namespace App\Http\Controllers;

use App\Models\UserNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        return view('notifications.index', [
            'notifications' => $request->user()->userNotifications()->latest()->paginate(15),
        ]);
    }

    /** يفتح الإشعار: يعلّمه مقروءًا ثم ينتقل إلى وجهته. لا يصل المستخدم إلا لإشعاراته هو. */
    public function open(Request $request, int $notification): RedirectResponse
    {
        $item = $request->user()->userNotifications()->findOrFail($notification);
        $item->markAsRead();

        // الوجهة تُنشأ داخليًا من الخادم، ونتأكد أنها ضمن الموقع نفسه
        $target = $item->url && str_starts_with($item->url, url('/')) ? $item->url : route('notifications.index');

        return redirect()->to($target);
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->userNotifications()->unread()->update(['read_at' => now()]);

        return back();
    }
}
