<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Envio síncrono nesta fase. Pode passar a implementar ShouldQueue depois.
 */
class ResetPasswordNotification extends Notification
{
    use Queueable;

    public function __construct(public string $token) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $email = method_exists($notifiable, 'getEmailForPasswordReset')
            ? $notifiable->getEmailForPasswordReset()
            : (string) $notifiable->email;

        $url = rtrim((string) config('app.frontend_url'), '/').'/reset-password?'.http_build_query([
            'token' => $this->token,
            'email' => $email,
        ]);

        $expireMinutes = (int) config('auth.passwords.users.expire', 60);

        return (new MailMessage)
            ->subject('Redefinição de palavra-passe — '.app(\App\Services\SettingsService::class)->salonName())
            ->markdown('emails.reset-password', [
                'url' => $url,
                'expireMinutes' => $expireMinutes,
                'salonName' => app(\App\Services\SettingsService::class)->salonName(),
                'userName' => $notifiable->full_name ?? $email,
            ]);
    }
}
