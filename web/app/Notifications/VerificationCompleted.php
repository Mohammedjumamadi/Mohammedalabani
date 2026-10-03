<?php

namespace App\Notifications;

use App\Models\VerificationRequest;
use App\Notifications\Channels\UserNotificationChannel;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VerificationCompleted extends Notification
{
    public function __construct(public readonly VerificationRequest $request) {}

    public function via(object $notifiable): array
    {
        return [UserNotificationChannel::class, 'mail'];
    }

    public function toUserNotification(object $notifiable): array
    {
        $status = $this->request->status;

        return [
            'type' => 'verification.completed',
            'title' => __('notifications.completed.title', [], $notifiable->locale),
            'body' => __('notifications.completed.body', [
                'file' => $this->request->mediaFile->original_name,
                'status' => __('enums.request_status.'.$status->value, [], $notifiable->locale),
            ], $notifiable->locale),
            'url' => route('verifications.show', $this->request),
            'data' => ['reference' => $this->request->reference_number],
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $locale = $notifiable->locale;

        return (new MailMessage)
            ->subject(__('notifications.completed.title', [], $locale))
            ->greeting(__('notifications.greeting', ['name' => $notifiable->name], $locale))
            ->line(__('notifications.completed.body', [
                'file' => $this->request->mediaFile->original_name,
                'status' => __('enums.request_status.'.$this->request->status->value, [], $locale),
            ], $locale))
            ->action(__('notifications.open_result', [], $locale), route('verifications.show', $this->request));
    }
}
