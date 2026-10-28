<?php

namespace Tests\Feature\Admin;

use App\Enums\RoleNames;
use App\Enums\UserStatuses;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\ClientInvitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ClientsImportTest extends TestCase
{
    use RefreshDatabase;

    private const HEADER = [
        '№', 'Наша назва', 'ЄДРПОУ', 'Договори (з 2020)', 'Стан у ЄДР', 'Дані ЄДР станом на', 'Назва в ЄДР',
        'Керівник', 'Адреса (ЄДР)', "Пов'язані організації", 'Контактна особа (Prozorro)', 'Телефон', 'Email',
        'Сайт', 'Дата закупівлі', 'Примітка',
    ];

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate(RoleNames::ADMIN);
        Role::findOrCreate(RoleNames::USER);
        $this->admin = User::factory()->create();
        $this->admin->syncRoles([RoleNames::ADMIN]);
    }

    public function test_only_registered_clients_are_imported_with_registry_name_and_details(): void
    {
        $this->import([
            $this->row('11111111', 'Зареєстровано', 'Наша лікарня', 'КНП "ЛІКАРНЯ"', 'lik@example.com', '1515/м, 1573/м'),
            $this->row('22222222', 'Припинено', 'Закрита', 'ЗАКРИТА', 'old@example.com'),
            $this->row('33333333', 'В стані припинення', 'Майже закрита', 'МАЙЖЕ', 'x@example.com'),
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Додано нових установ: 1, доповнено даними: 0. Пропущено (не «Зареєстровано» у ЄДР): 2.');

        $organization = Organization::sole();
        $this->assertSame('КНП "ЛІКАРНЯ"', $organization->name);
        $this->assertSame('Наша лікарня', $organization->our_name);
        $this->assertSame('lik@example.com', $organization->contact_email);
        $this->assertSame('1515/м, 1573/м', $organization->contracts);
        $this->assertSame('Іваненко Іван', $organization->contact_person);
    }

    public function test_reimport_fills_only_empty_fields_and_keeps_manual_edits(): void
    {
        $organization = Organization::create([
            'identifier' => 'org000000001', 'name' => 'Назва, яку ввів адміністратор', 'edrpou' => '11111111',
            'contact_email' => 'new@example.com',
        ]);

        $this->import([$this->row('11111111', 'Зареєстровано', 'Наша лікарня', 'КНП "ЛІКАРНЯ"', 'old@example.com')])
            ->assertOk()
            ->assertJsonPath('message', 'Додано нових установ: 0, доповнено даними: 1. Пропущено (не «Зареєстровано» у ЄДР): 0.');

        $organization->refresh();
        $this->assertSame('Назва, яку ввів адміністратор', $organization->name);
        $this->assertSame('new@example.com', $organization->contact_email);
        $this->assertSame('Наша лікарня', $organization->our_name);
        $this->assertSame('Іваненко Іван', $organization->contact_person);
    }

    public function test_file_without_expected_columns_is_rejected(): void
    {
        $this->actingAs($this->admin)
            ->postJson('/api/admin/organizations/import-clients', ['file' => $this->workbook([['Код', 'Назва']])])
            ->assertStatus(422);

        $this->assertSame(0, Organization::count());
    }

    public function test_invite_emails_contact_and_marks_organization_invited(): void
    {
        Notification::fake();
        $organization = Organization::create([
            'identifier' => 'org000000001', 'name' => 'КНП "ЛІКАРНЯ"', 'edrpou' => '11111111', 'contact_email' => 'lik@example.com',
        ]);

        $this->actingAs($this->admin)
            ->postJson("/api/admin/organizations/{$organization->identifier}/invite")
            ->assertOk()
            ->assertJsonPath('status', 'invited')
            ->assertJsonPath('message', 'Запрошення надіслано на lik@example.com.');

        Notification::assertSentTo(
            new AnonymousNotifiable,
            ClientInvitation::class,
            fn ($notification, $channels, $notifiable) => $notifiable->routes['mail'] === 'lik@example.com'
        );
        $this->assertNotNull($organization->refresh()->invited_at);
        $this->assertStringContainsString('edrpou=11111111', ClientInvitation::registrationUrl($organization));
    }

    public function test_invite_without_email_is_rejected(): void
    {
        Notification::fake();
        $organization = Organization::create(['identifier' => 'org000000001', 'name' => 'Без пошти', 'edrpou' => '11111111']);

        $this->actingAs($this->admin)
            ->postJson("/api/admin/organizations/{$organization->identifier}/invite")
            ->assertStatus(422);

        Notification::assertNothingSent();
        $this->assertNull($organization->refresh()->invited_at);
    }

    public function test_status_follows_invitation_and_users(): void
    {
        $organization = Organization::create(['identifier' => 'org000000001', 'name' => 'Лікарня', 'edrpou' => '11111111']);
        $statusOf = fn () => collect($this->actingAs($this->admin)->getJson('/api/admin/organizations')->json())
            ->firstWhere('identifier', $organization->identifier)['status'];

        $this->assertSame('not_invited', $statusOf());

        $organization->forceFill(['invited_at' => now()])->save();
        $this->assertSame('invited', $statusOf());

        $user = User::factory()->create(['organization_id' => $organization->id, 'status' => UserStatuses::PENDING]);
        $this->assertSame('applied', $statusOf());

        $user->update(['status' => UserStatuses::ACTIVE]);
        $this->assertSame('active', $statusOf());
    }

    private function import(array $rows)
    {
        return $this->actingAs($this->admin)
            ->postJson('/api/admin/organizations/import-clients', ['file' => $this->workbook([self::HEADER, ...$rows])]);
    }

    private function row(string $edrpou, string $state, string $ourName, string $registryName, string $email, string $contracts = ''): array
    {
        return [
            '1', $ourName, $edrpou, $contracts, $state, '01.10.2026', $registryName, 'ПЕТРЕНКО ПЕТРО',
            'м. Київ', '', 'Іваненко Іван', '+380501112233', $email, '', '', '',
        ];
    }

    private function workbook(array $rows): UploadedFile
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        foreach ($rows as $index => $row) {
            foreach (array_values($row) as $column => $value) {
                if ($value !== '') {
                    $sheet->setCellValueExplicit([$column + 1, $index + 1], $value, DataType::TYPE_STRING);
                }
            }
        }

        $path = tempnam(sys_get_temp_dir(), 'clients').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return new UploadedFile($path, 'clients.xlsx', null, null, true);
    }
}
