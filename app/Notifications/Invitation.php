<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class Invitation extends Notification
{
    public function __construct(
        private readonly string $token
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        // Same SPA form as password reset; `invite` makes it use the longer-lived invites broker.
        $url = rtrim(config('app.frontend_url'), '/').'/reset-password?'.http_build_query([
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
            'invite' => 1,
        ]);
        $days = intdiv(config('auth.passwords.invites.expire'), 60 * 24);

        return (new MailMessage)
            ->subject('Запрошення до системи')
            ->greeting('Вітаємо!')
            ->line('Для Вас створено обліковий запис. Щоб почати роботу, встановіть свій пароль.')
            ->action('Встановити пароль', $url)
            ->line("Посилання дійсне {$days} днів. Після цього можна скористатися функцією «Забули пароль?» на сторінці входу.")
            ->salutation('З повагою, '.config('app.name'));
    }
}
