<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewRegistrationRequest extends Notification
{
    public function __construct(
        private readonly User $applicant
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $organization = $this->applicant->organization;

        return (new MailMessage)
            ->subject('Нова заявка на доступ')
            ->greeting('Вітаємо!')
            ->line('Надійшла нова заявка на доступ до системи:')
            ->line("Установа: {$organization->name} (ЄДРПОУ {$organization->edrpou})")
            ->line("Ім'я: {$this->applicant->name}")
            ->line("Email: {$this->applicant->email}")
            ->action('Переглянути заявки', rtrim(config('app.frontend_url'), '/').'/admin/users')
            ->salutation('З повагою, '.config('app.name'));
    }
}
