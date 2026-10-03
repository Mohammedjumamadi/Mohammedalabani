<?php

namespace App\Notifications;

use App\Models\VerificationRequest;
use App\Notifications\Channels\UserNotificationChannel;
use Illuminate\Notifications\Notification;

class VerificationFailed extends Notification
{
    public function __construct(public readonly VerificationRequest $request) {}

    public function via(object $notifiable): array
    {
        return [UserNotificationChannel::class];
    }

    public function toUserNotification(object $notifiable): array
    {
        return [
            'type' => 'verification.failed',
            'title' => __('notifications.failed.title', [], $notifiable->locale),
            'body' => __('notifications.failed.body', ['file' => $this->request->mediaFile->original_name], $notifiable->locale),
            'url' => route('verifications.show', $this->request),
            'data' => ['reference' => $this->request->reference_number],
        ];
    }
}
