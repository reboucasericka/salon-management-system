<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\Professional;
use App\Models\Service;
use App\Models\User;
use App\Services\CommissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CommissionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $professionalUser;

    private User $clientUser;

    private Professional $professional;

    private Service $cut;

    private Service $color;

    private CommissionService $commissions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->commissions = app(CommissionService::class);

        $this->admin = User::create([
            'first_name' => 'Admin',
            'last_name' => 'Commission',
            'email' => 'admin-commission@test.com',
            'role' => UserRole::Admin,
            'is_active' => true,
            'password' => Hash::make('password'),
        ]);

        $this->professionalUser = User::create([
            'first_name' => 'Pro',
            'last_name' => 'Commission',
            'email' => 'pro-commission@test.com',
            'role' => UserRole::Professional,
            'is_active' => true,
            'password' => Hash::make('password'),
        ]);

        $this->clientUser = User::create([
            'first_name' => 'Client',
            'last_name' => 'Commission',
            'email' => 'client-commission@test.com',
            'role' => UserRole::Client,
            'is_active' => true,
            'password' => Hash::make('password'),
        ]);

        $this->professional = Professional::create([
            'user_id' => $this->professionalUser->id,
            'name' => 'Ana Comissão',
            'specialty' => 'Cabelo',
            'commission_percentage' => 20,
            'is_active' => true,
        ]);

        $this->cut = Service::create([
            'name' => 'Corte',
            'price' => 50,
            'duration_minutes' => 45,
            'is_active' => true,
        ]);

        $this->color = Service::create([
            'name' => 'Coloração',
            'price' => 100,
            'duration_minutes' => 90,
            'is_active' => true,
        ]);
    }

    public function test_specific_pivot_commission_overrides_professional_default(): void
    {
        $this->professional->services()->attach([
            $this->cut->id => ['commission_percentage' => 30],
            $this->color->id => ['commission_percentage' => null],
        ]);

        $this->assertSame(
            '30.00',
            $this->commissions->resolveCommissionPercentage($this->professional, $this->cut),
        );

        $this->assertSame(
            '20.00',
            $this->commissions->resolveCommissionPercentage($this->professional, $this->color),
        );
    }

    public function test_explicit_zero_does_not_fall_back(): void
    {
        $this->professional->services()->attach([
            $this->cut->id => ['commission_percentage' => 0],
        ]);

        $this->assertSame(
            '0.00',
            $this->commissions->resolveCommissionPercentage($this->professional, $this->cut),
        );
    }

    public function test_unassociated_service_does_not_resolve(): void
    {
        $this->assertNull(
            $this->commissions->resolveCommissionPercentage($this->professional, $this->cut),
        );
    }

    public function test_calculate_commission_amount(): void
    {
        $this->assertSame('10.00', $this->commissions->calculateCommissionAmount(50, 20));
        $this->assertSame('0.00', $this->commissions->calculateCommissionAmount(50, 0));
    }

    public function test_sync_new_payload_sets_commissions_and_legacy_preserves_them(): void
    {
        Sanctum::actingAs($this->admin);

        $this->putJson("/api/v1/professionals/{$this->professional->id}/services", [
            'services' => [
                ['service_id' => $this->cut->id, 'commission_percentage' => 25],
                ['service_id' => $this->color->id, 'commission_percentage' => null],
            ],
        ])->assertOk()
            ->assertJsonPath('data.services_count', 2);

        $this->assertDatabaseHas('professional_service', [
            'professional_id' => $this->professional->id,
            'service_id' => $this->cut->id,
            'commission_percentage' => 25,
        ]);

        $this->getJson("/api/v1/professionals/{$this->professional->id}/services")
            ->assertOk()
            ->assertJsonFragment([
                'id' => $this->cut->id,
                'commission_percentage' => '25.00',
            ]);

        $this->putJson("/api/v1/professionals/{$this->professional->id}/services", [
            'service_ids' => [$this->cut->id, $this->color->id],
        ])->assertOk();

        $this->assertDatabaseHas('professional_service', [
            'professional_id' => $this->professional->id,
            'service_id' => $this->cut->id,
            'commission_percentage' => 25,
        ]);
    }

    public function test_invalid_commission_percentage_returns_422(): void
    {
        Sanctum::actingAs($this->admin);

        $this->putJson("/api/v1/professionals/{$this->professional->id}/services", [
            'services' => [
                ['service_id' => $this->cut->id, 'commission_percentage' => 150],
            ],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['services.0.commission_percentage']);
    }

    public function test_appointment_calculation_uses_total_price_when_present(): void
    {
        $this->professional->services()->attach([
            $this->cut->id => ['commission_percentage' => 10],
        ]);

        $client = Client::create([
            'name' => 'Cliente Comissão',
            'is_active' => true,
        ]);

        $appointment = Appointment::create([
            'client_id' => $client->id,
            'service_id' => $this->cut->id,
            'professional_id' => $this->professional->id,
            'start_time' => now()->subHour(),
            'end_time' => now(),
            'status' => Appointment::STATUS_COMPLETED,
            'total_price' => 80,
            'is_active' => true,
        ]);

        $calc = $this->commissions->calculateForAppointment($appointment);

        $this->assertTrue($calc['applicable']);
        $this->assertSame('80.00', $calc['base_amount']);
        $this->assertSame('10.00', $calc['percentage']);
        $this->assertSame('8.00', $calc['commission_amount']);
        $this->assertSame('professional_service', $calc['source']);
    }

    public function test_preview_endpoint_admin_only_and_lists_calculated_rows(): void
    {
        $this->professional->services()->attach([
            $this->cut->id => ['commission_percentage' => null],
        ]);

        $client = Client::create([
            'name' => 'Cliente Preview',
            'is_active' => true,
        ]);

        Appointment::create([
            'client_id' => $client->id,
            'service_id' => $this->cut->id,
            'professional_id' => $this->professional->id,
            'start_time' => now()->subDay(),
            'end_time' => now()->subDay()->addHour(),
            'status' => Appointment::STATUS_COMPLETED,
            'total_price' => null,
            'is_active' => true,
        ]);

        Appointment::create([
            'client_id' => $client->id,
            'service_id' => $this->cut->id,
            'professional_id' => $this->professional->id,
            'start_time' => now()->subDays(2),
            'end_time' => now()->subDays(2)->addHour(),
            'status' => Appointment::STATUS_CANCELED,
            'total_price' => 50,
            'is_active' => true,
        ]);

        Sanctum::actingAs($this->admin);
        $this->getJson('/api/v1/commissions')
            ->assertOk()
            ->assertJsonPath('meta.mode', 'calculated_preview')
            ->assertJsonPath('meta.count', 1)
            ->assertJsonPath('meta.total_commission', '10.00');

        Sanctum::actingAs($this->professionalUser);
        $this->getJson('/api/v1/commissions')->assertForbidden();

        Sanctum::actingAs($this->clientUser);
        $this->getJson('/api/v1/commissions')->assertForbidden();
    }

    public function test_generate_creates_payable_once_and_is_idempotent(): void
    {
        $this->professional->services()->attach([
            $this->cut->id => ['commission_percentage' => 20],
        ]);

        $client = Client::create([
            'name' => 'Cliente Generate',
            'is_active' => true,
        ]);

        $appointment = Appointment::create([
            'client_id' => $client->id,
            'service_id' => $this->cut->id,
            'professional_id' => $this->professional->id,
            'start_time' => now()->subDay(),
            'end_time' => now()->subDay()->addHour(),
            'status' => Appointment::STATUS_COMPLETED,
            'total_price' => 50,
            'is_active' => true,
        ]);

        Sanctum::actingAs($this->admin);

        $first = $this->postJson('/api/v1/commissions/generate', [
            'appointment_ids' => [$appointment->id],
        ])->assertCreated()
            ->assertJsonPath('meta.created_count', 1)
            ->assertJsonPath('meta.skipped_count', 0)
            ->json('data.created.0');

        $this->assertDatabaseHas('payables', [
            'id' => $first['payable_id'],
            'type' => 'commission',
            'appointment_id' => $appointment->id,
            'professional_id' => $this->professional->id,
            'amount' => 10,
            'status' => 'pending',
        ]);

        $this->postJson('/api/v1/commissions/generate', [
            'appointment_ids' => [$appointment->id],
        ])->assertCreated()
            ->assertJsonPath('meta.created_count', 0)
            ->assertJsonPath('meta.skipped_count', 1)
            ->assertJsonPath('data.skipped.0.reason', 'already_exists');

        $this->getJson('/api/v1/commissions')
            ->assertOk()
            ->assertJsonPath('data.0.financial_status', 'payable_pending')
            ->assertJsonPath('data.0.payable_id', $first['payable_id']);

        $this->postJson("/api/v1/payables/{$first['payable_id']}/pay")
            ->assertOk();

        $this->getJson('/api/v1/commissions')
            ->assertOk()
            ->assertJsonPath('data.0.financial_status', 'paid');
    }

    public function test_generate_skips_canceled_and_zero_commission(): void
    {
        $this->professional->services()->attach([
            $this->cut->id => ['commission_percentage' => 0],
            $this->color->id => ['commission_percentage' => 10],
        ]);

        $client = Client::create([
            'name' => 'Cliente Skip',
            'is_active' => true,
        ]);

        $zero = Appointment::create([
            'client_id' => $client->id,
            'service_id' => $this->cut->id,
            'professional_id' => $this->professional->id,
            'start_time' => now()->subDay(),
            'end_time' => now()->subDay()->addHour(),
            'status' => Appointment::STATUS_COMPLETED,
            'total_price' => 50,
            'is_active' => true,
        ]);

        $canceled = Appointment::create([
            'client_id' => $client->id,
            'service_id' => $this->color->id,
            'professional_id' => $this->professional->id,
            'start_time' => now()->subDays(2),
            'end_time' => now()->subDays(2)->addHour(),
            'status' => Appointment::STATUS_CANCELED,
            'total_price' => 100,
            'is_active' => true,
        ]);

        Sanctum::actingAs($this->admin);

        $response = $this->postJson('/api/v1/commissions/generate', [
            'appointment_ids' => [$zero->id, $canceled->id],
        ])->assertCreated()
            ->assertJsonPath('meta.created_count', 0)
            ->assertJsonPath('meta.skipped_count', 2);

        $reasons = collect($response->json('data.skipped'))->pluck('reason')->sort()->values()->all();
        $this->assertSame(['canceled', 'zero_amount'], $reasons);

        $this->assertDatabaseMissing('payables', [
            'appointment_id' => $zero->id,
        ]);
        $this->assertDatabaseMissing('payables', [
            'appointment_id' => $canceled->id,
        ]);
    }

    public function test_professional_cannot_generate_commissions(): void
    {
        Sanctum::actingAs($this->professionalUser);

        $this->postJson('/api/v1/commissions/generate', [
            'appointment_ids' => [1],
        ])->assertForbidden();
    }
}
