<?php

namespace Tests\Feature;

use App\Enums\PayableStatus;
use App\Enums\PayableType;
use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\Professional;
use App\Models\Sale;
use App\Models\Service;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReportsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Professional $professional;

    private Service $service;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.timezone' => 'UTC', 'cache.default' => 'array']);
        Carbon::setTestNow(Carbon::parse('2026-08-15 12:00:00', 'UTC'));

        $this->admin = User::factory()->admin()->create();

        $proUser = User::create([
            'first_name' => 'Ana',
            'last_name' => 'Pro',
            'email' => 'ana-reports@salon.test',
            'role' => UserRole::Professional,
            'is_active' => true,
            'password' => Hash::make('password'),
        ]);

        $this->professional = Professional::create([
            'user_id' => $proUser->id,
            'name' => 'Ana Pro',
            'specialty' => 'Cabeleireira',
            'email' => 'ana-reports@salon.test',
            'commission_percentage' => 20,
            'is_active' => true,
        ]);

        $this->service = Service::create([
            'name' => 'Corte',
            'description' => 'Corte',
            'price' => 50,
            'duration_minutes' => 60,
            'is_active' => true,
        ]);

        $this->professional->services()->attach($this->service->id, [
            'commission_percentage' => 20,
        ]);

        $this->client = Client::create([
            'name' => 'Cliente R',
            'email' => 'cliente-r@example.com',
            'is_active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_admin_can_get_overview_and_exports(): void
    {
        Sanctum::actingAs($this->admin);

        $this->getJson('/api/v1/reports/overview?from=2026-08-01&to=2026-08-31')
            ->assertOk()
            ->assertJsonPath('data.meta.currency', 'EUR')
            ->assertJsonStructure(['data' => ['meta', 'summary', 'top_services']]);

        $csv = $this->get('/api/v1/reports/overview?from=2026-08-01&to=2026-08-31&format=csv');
        $csv->assertOk();
        $this->assertStringContainsString('text/csv', (string) $csv->headers->get('content-type'));

        $pdf = $this->get('/api/v1/reports/overview?from=2026-08-01&to=2026-08-31&format=pdf');
        $pdf->assertOk();
        $this->assertStringContainsString('pdf', strtolower((string) $pdf->headers->get('content-type')));
    }

    public function test_appointments_report_filters_and_totals(): void
    {
        Appointment::create([
            'client_id' => $this->client->id,
            'service_id' => $this->service->id,
            'professional_id' => $this->professional->id,
            'start_time' => Carbon::parse('2026-08-10 10:00:00'),
            'end_time' => Carbon::parse('2026-08-10 11:00:00'),
            'status' => Appointment::STATUS_COMPLETED,
            'total_price' => 50,
            'is_active' => true,
        ]);
        Appointment::create([
            'client_id' => $this->client->id,
            'service_id' => $this->service->id,
            'professional_id' => $this->professional->id,
            'start_time' => Carbon::parse('2026-08-11 10:00:00'),
            'end_time' => Carbon::parse('2026-08-11 11:00:00'),
            'status' => Appointment::STATUS_CANCELED,
            'total_price' => 50,
            'is_active' => false,
        ]);

        Sanctum::actingAs($this->admin);

        $this->getJson('/api/v1/reports/appointments?from=2026-08-01&to=2026-08-31')
            ->assertOk()
            ->assertJsonPath('data.summary.total', 2)
            ->assertJsonPath('data.summary.completed', 1)
            ->assertJsonPath('data.summary.canceled', 1)
            ->assertJsonPath('data.summary.revenue_completed', '50.00');

        $this->getJson('/api/v1/reports/appointments?from=2026-08-01&to=2026-08-31&status=completed')
            ->assertOk()
            ->assertJsonPath('data.summary.total', 1);
    }

    public function test_sales_report_totals(): void
    {
        Sale::create([
            'client_id' => $this->client->id,
            'user_id' => $this->admin->id,
            'total_amount' => 100,
            'payment_method' => PaymentMethod::Cash,
            'status' => SaleStatus::Paid,
        ]);
        Sale::create([
            'client_id' => $this->client->id,
            'user_id' => $this->admin->id,
            'total_amount' => 40,
            'payment_method' => PaymentMethod::Card,
            'status' => SaleStatus::Pending,
        ]);

        Sanctum::actingAs($this->admin);

        $this->getJson('/api/v1/reports/sales?from=2026-08-01&to=2026-08-31')
            ->assertOk()
            ->assertJsonPath('data.summary.total_sales', 2)
            ->assertJsonPath('data.summary.paid_sales', 1)
            ->assertJsonPath('data.summary.paid_amount', '100.00');
    }

    public function test_commissions_report_uses_commission_service(): void
    {
        Appointment::create([
            'client_id' => $this->client->id,
            'service_id' => $this->service->id,
            'professional_id' => $this->professional->id,
            'start_time' => Carbon::parse('2026-08-12 10:00:00'),
            'end_time' => Carbon::parse('2026-08-12 11:00:00'),
            'status' => Appointment::STATUS_COMPLETED,
            'total_price' => 100,
            'is_active' => true,
        ]);

        Sanctum::actingAs($this->admin);

        $this->getJson('/api/v1/reports/commissions?from=2026-08-01&to=2026-08-31')
            ->assertOk()
            ->assertJsonPath('data.summary.preview_count', 1)
            ->assertJsonPath('data.summary.preview_total', '20.00');
    }

    public function test_financial_and_cash_reports(): void
    {
        Sale::create([
            'client_id' => $this->client->id,
            'user_id' => $this->admin->id,
            'total_amount' => 200,
            'payment_method' => PaymentMethod::Cash,
            'status' => SaleStatus::Paid,
        ]);

        \App\Models\Payable::create([
            'description' => 'Despesa',
            'amount' => 50,
            'due_date' => '2026-08-20',
            'type' => PayableType::Expense,
            'status' => PayableStatus::Paid,
            'paid_at' => now(),
            'created_by' => $this->admin->id,
            'paid_by' => $this->admin->id,
        ]);

        Sanctum::actingAs($this->admin);

        $this->getJson('/api/v1/reports/financial?from=2026-08-01&to=2026-08-31')
            ->assertOk()
            ->assertJsonPath('data.summary.sales_paid', '200.00')
            ->assertJsonPath('data.summary.payables_paid', '50.00')
            ->assertJsonPath('data.summary.operational_result', '150.00');

        $this->getJson('/api/v1/reports/cash?year=2026&month=8')
            ->assertOk()
            ->assertJsonStructure(['data' => ['meta', 'summary', 'days']]);
    }

    public function test_professional_and_client_forbidden(): void
    {
        $professional = User::factory()->professional()->create();
        $client = User::factory()->client()->create();

        Sanctum::actingAs($professional);
        $this->getJson('/api/v1/reports/overview')->assertForbidden();

        Sanctum::actingAs($client);
        $this->getJson('/api/v1/reports/sales')->assertForbidden();
    }

    public function test_empty_state_ok(): void
    {
        Sanctum::actingAs($this->admin);

        $this->getJson('/api/v1/reports/appointments?from=2026-01-01&to=2026-01-02')
            ->assertOk()
            ->assertJsonPath('data.summary.total', 0)
            ->assertJsonCount(0, 'data.rows');
    }
}
