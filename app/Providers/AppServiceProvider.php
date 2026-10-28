<?php

namespace App\Providers;

use App\Support\MailGuard;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(MessageSending::class, [MailGuard::class, 'handle']);

        // The reset form lives in the SPA, not in this API.
        ResetPassword::createUrlUsing(function ($user, string $token) {
            return rtrim(config('app.frontend_url'), '/').'/reset-password?'.http_build_query([
                'token' => $token,
                'email' => $user->getEmailForPasswordReset(),
            ]);
        });

        ResetPassword::toMailUsing(function ($notifiable, string $token) {
            $expire = config('auth.passwords.'.config('auth.defaults.passwords').'.expire');

            return (new MailMessage)
                ->subject('Відновлення пароля')
                ->greeting('Вітаємо!')
                ->line('Ви отримали цей лист, бо для Вашого облікового запису надійшов запит на зміну пароля.')
                ->action('Змінити пароль', call_user_func(ResetPassword::$createUrlCallback, $notifiable, $token))
                ->line("Посилання дійсне {$expire} хвилин.")
                ->line('Якщо Ви не надсилали запит, просто проігноруйте цей лист.')
                ->salutation('З повагою, '.config('app.name'));
        });
    }
}
