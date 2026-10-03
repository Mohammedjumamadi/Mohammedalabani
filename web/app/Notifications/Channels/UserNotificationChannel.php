<?php

namespace App\Notifications\Channels;

use Illuminate\Notifications\Notification;

/** قناة إشعارات تحفظ الإشعار في جدول user_notifications داخل المنصة. */
class UserNotificationChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        /** @var array{type: string, title: string, body?: string, url?: string, data?: array} $payload */
        $payload = $notification->toUserNotification($notifiable);

        $notifiable->userNotifications()->create($payload);
    }
}
