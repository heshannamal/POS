<?php

namespace App\Notifications;

use App\Services\PosEmailVariables;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PosPasswordResetNotification extends Notification
{
    public function __construct(private readonly string $token)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);
        $vars = PosEmailVariables::resetVariables($notifiable, $url);

        return (new MailMessage())
            ->subject(PosEmailVariables::render('password_reset', 'subject', $vars))
            ->greeting(PosEmailVariables::render('password_reset', 'greeting', $vars))
            ->line(PosEmailVariables::render('password_reset', 'intro', $vars))
            ->action(PosEmailVariables::render('password_reset', 'action', $vars), $url)
            ->line(PosEmailVariables::render('password_reset', 'outro', $vars));
    }
}
