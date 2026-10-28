<?php

namespace App\Support;

use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mime\Address;

/**
 * Mail test mode: see config/mailguard.php.
 */
class MailGuard
{
    public static function isSafeMode(): bool
    {
        return (bool) config('mailguard.safe_mode');
    }

    public static function allows(?string $address): bool
    {
        if (! self::isSafeMode()) {
            return true;
        }

        return $address !== null && in_array(mb_strtolower(trim($address)), config('mailguard.allowed_recipients'), true);
    }

    public static function allowedList(): string
    {
        return implode(', ', config('mailguard.allowed_recipients')) ?: 'жодної адреси';
    }

    /**
     * Last line of defence for every outgoing message; returning false cancels the send.
     */
    public static function handle(MessageSending $event): ?bool
    {
        $recipients = array_map(
            fn (Address $address) => $address->getAddress(),
            [...$event->message->getTo(), ...$event->message->getCc(), ...$event->message->getBcc()]
        );

        $blocked = array_filter($recipients, fn ($address) => ! self::allows($address));
        if (empty($blocked)) {
            return null;
        }

        Log::warning('Mail test mode: message not sent', [
            'subject' => $event->message->getSubject(),
            'blocked' => array_values($blocked),
        ]);

        return false;
    }
}
