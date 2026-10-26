<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class PasswordResetController extends Controller
{
    public function sendResetLink(ForgotPasswordRequest $request)
    {
        Password::sendResetLink($request->only('email'));

        // Same response whether or not the email exists, so the form can't be
        // used to find out who has an account.
        return $this->successResponse('Якщо цей email зареєстровано, ми надіслали на нього посилання для зміни пароля.');
    }

    public function reset(ResetPasswordRequest $request)
    {
        // Invitation links come from the longer-lived invites broker; both share one token table.
        $broker = Password::broker($request->boolean('invite') ? 'invites' : null);

        $status = $broker->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->password = $password;
                $user->setRememberToken(Str::random(60));
                $user->save();

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return $this->validationErrorResponse('Посилання для зміни пароля недійсне або його термін дії минув.');
        }

        return $this->successResponse('Пароль змінено. Тепер увійдіть з новим паролем.');
    }
}
