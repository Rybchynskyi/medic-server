<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_link_is_sent_and_points_to_frontend(): void
    {
        Notification::fake();
        config(['app.frontend_url' => 'https://app.example.com']);
        $user = User::factory()->create(['email' => 'ivan@example.com']);

        $this->postJson('/api/forgot-password', ['email' => 'ivan@example.com'])->assertOk();

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
            $url = $notification->toMail($user)->actionUrl;

            return str_starts_with($url, 'https://app.example.com/reset-password?token=')
                && str_contains($url, 'email=ivan%40example.com');
        });
    }

    public function test_unknown_email_gets_the_same_response_and_no_notification(): void
    {
        Notification::fake();

        $this->postJson('/api/forgot-password', ['email' => 'nobody@example.com'])
            ->assertOk()
            ->assertJsonStructure(['message']);

        Notification::assertNothingSent();
    }

    public function test_user_can_reset_password_with_valid_token(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'ivan@example.com', 'password' => bcrypt('old-password')]);

        $this->postJson('/api/forgot-password', ['email' => 'ivan@example.com']);

        $token = null;
        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use (&$token) {
            $token = $notification->token;

            return true;
        });

        $this->postJson('/api/reset-password', [
            'token' => $token,
            'email' => 'ivan@example.com',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertOk();

        $this->assertTrue(Hash::check('new-password', $user->fresh()->password));
    }

    public function test_reset_with_invalid_token_fails(): void
    {
        $user = User::factory()->create(['email' => 'ivan@example.com', 'password' => bcrypt('old-password')]);

        $this->postJson('/api/reset-password', [
            'token' => 'invalid-token',
            'email' => 'ivan@example.com',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertStatus(422);

        $this->assertTrue(Hash::check('old-password', $user->fresh()->password));
    }
}
