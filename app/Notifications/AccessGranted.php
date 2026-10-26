<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AccessGranted extends Notification
{
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Доступ відкрито')
            ->greeting('Вітаємо!')
            ->line('Вашу заявку підтверджено. Тепер Ви можете увійти в систему зі своїм email та паролем.')
            ->action('Увійти', rtrim(config('app.frontend_url'), '/').'/login')
            ->salutation('З повагою, '.config('app.name'));
    }
}
