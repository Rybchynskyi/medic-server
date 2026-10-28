<?php

namespace Tests\Feature;

use App\Enums\RoleNames;
use App\Enums\UserStatuses;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MailGuardTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        config(['mailguard.safe_mode' => true, 'mailguard.allowed_recipients' => ['tester@example.com']]);

        Role::findOrCreate(RoleNames::ADMIN);
        Role::findOrCreate(RoleNames::USER);
        $this->admin = User::factory()->create(['email' => 'admin@example.com']);
        $this->admin->syncRoles([RoleNames::ADMIN]);
    }

    public function test_invite_to_address_outside_allow_list_is_refused_and_not_marked(): void
    {
        $organization = Organization::create([
            'identifier' => 'org000000001', 'name' => 'Лікарня', 'edrpou' => '11111111', 'contact_email' => 'client@hospital.ua',
        ]);

        $this->actingAs($this->admin)
            ->postJson("/api/admin/organizations/{$organization->identifier}/invite")
            ->assertStatus(422)
            ->assertJsonPath('errors.contactEmail.0', 'Тестовий режим пошти: лист на client@hospital.ua не надіслано. Дозволено лише: tester@example.com.');

        $this->assertNull($organization->refresh()->invited_at);
        $this->assertCount(0, $this->sentMessages());
    }

    public function test_invite_to_allowed_address_is_delivered(): void
    {
        $organization = Organization::create([
            'identifier' => 'org000000001', 'name' => 'Лікарня', 'edrpou' => '11111111', 'contact_email' => 'Tester@Example.com',
        ]);

        $this->actingAs($this->admin)
            ->postJson("/api/admin/organizations/{$organization->identifier}/invite")
            ->assertOk();

        $this->assertCount(1, $this->sentMessages());
    }

    public function test_any_other_mail_to_outside_address_is_dropped(): void
    {
        $user = User::factory()->create(['email' => 'client@hospital.ua', 'status' => UserStatuses::PENDING]);

        $this->actingAs($this->admin)
            ->postJson("/api/admin/users/{$user->identifier}/approve")
            ->assertOk();

        // Sent directly, bypassing the services' checks: the MessageSending listener still drops it.
        Mail::raw('test', fn ($message) => $message->to('someone@else.ua')->subject('Тест'));

        $this->assertCount(0, $this->sentMessages());
    }

    private function sentMessages()
    {
        return app('mailer')->getSymfonyTransport()->messages();
    }
}
