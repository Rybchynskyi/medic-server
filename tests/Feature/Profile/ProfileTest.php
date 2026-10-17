<?php

namespace Tests\Feature\Profile;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_update_own_name(): void
    {
        $user = User::factory()->create(['name' => 'Old Name']);
        $this->actingAs($user);

        $this->putJson('/api/user/profile', ['name' => 'New Name'])
            ->assertOk()
            ->assertJsonPath('name', 'New Name');

        $this->assertSame('New Name', $user->fresh()->name);
    }

    public function test_user_can_change_own_password_with_correct_current_password(): void
    {
        $user = User::factory()->create(['password' => bcrypt('old-password')]);
        $this->actingAs($user);

        $this->putJson('/api/user/password', [
            'current_password' => 'old-password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertOk();

        $this->assertTrue(Hash::check('new-password', $user->fresh()->password));
    }

    public function test_user_cannot_change_password_with_wrong_current_password(): void
    {
        $user = User::factory()->create(['password' => bcrypt('old-password')]);
        $this->actingAs($user);

        $this->putJson('/api/user/password', [
            'current_password' => 'wrong-password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertStatus(422);

        $this->assertTrue(Hash::check('old-password', $user->fresh()->password));
    }
}
