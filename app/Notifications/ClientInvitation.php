<?php

namespace App\Notifications;

use App\Models\Organization;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Offer to a client from the client base to apply for access; sent to the organization's contact email.
 */
class ClientInvitation extends Notification
{
    public function __construct(
        private readonly Organization $organization
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $sender = config('invitations.sender');

        return (new MailMessage)
            ->subject('Запрошення до сервісу розрахунку витрат матеріалів — '.$sender['organization'])
            ->greeting("Шановні колеги з {$this->organization->name}!")
            ->line($sender['organization'].' запрошує вас користуватися онлайн-сервісом для розрахунку витрат лікарських засобів і виробів медичного призначення на надані послуги.')
            ->line('Щоб отримати доступ, заповніть коротку заявку за посиланням — дані вашої установи вже внесено.')
            ->action('Подати заявку', self::registrationUrl($this->organization))
            ->line('Після перевірки заявки ми надішлемо вам підтвердження на email.')
            ->salutation(implode("\n\n", array_filter([
                'З повагою,',
                $sender['name'],
                $sender['position'],
                $sender['phones'] ? 'тел. '.$sender['phones'] : null,
                $sender['email'] ? 'e-mail: '.$sender['email'] : null,
            ])));
    }

    /**
     * Registration form link with the organization prefilled; also offered to the admin to send manually.
     */
    public static function registrationUrl(Organization $organization): string
    {
        return rtrim(config('app.frontend_url'), '/').'/register?'.http_build_query(array_filter([
            'edrpou' => $organization->edrpou,
            'organizationName' => $organization->name,
            'email' => $organization->contact_email,
        ]));
    }
}
