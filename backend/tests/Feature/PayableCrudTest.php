<?php

namespace Tests\Feature;

use App\Enums\PayableStatus;
use App\Enums\PayableType;
use App\Enums\UserRole;
use App\Models\Payable;
use App\Models\Professional;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PayableCrudTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $professional;

    private User $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'first_name' => 'Admin',
            'last_name' => 'Payable',
            'email' => 'admin-payable@test.com',
            'role' => UserRole::Admin,
            'is_active' => true,
            'password' => Hash::make('password'),
        ]);

        $this->professional = User::create([
            'first_name' => 'Pro',
            'last_name' => 'Payable',
            'email' => 'pro-payable@test.com',
            'role' => UserRole::Professional,
            'is_active' => true,
            'password' => Hash::make('password'),
        ]);

        $this->client = User::create([
            'first_name' => 'Client',
            'last_name' => 'Payable',
            'email' => 'client-payable@test.com',
            'role' => UserRole::Client,
            'is_active' => true,
            'password' => Hash::make('password'),
        ]);
    }

    public function test_admin_can_crud_payable(): void
    {
        Sanctum::actingAs($this->admin);

        $create = $this->postJson('/api/v1/payables', [
            'description' => 'Aluguer do espaço',
            'amount' => 250.50,
            'due_date' => now()->addDays(10)->toDateString(),
            'type' => 'expense',
            'notes' => 'Mensalidade',
        ]);

        $create->assertCreated()
            ->assertJsonPath('data.description', 'Aluguer do espaço')
            ->assertJsonPath('data.amount', '250.50')
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.type', 'expense')
            ->assertJsonPath('data.is_paid', false)
            ->assertJsonPath('data.created_by', $this->admin->id);

        $id = $create->json('data.id');

        $this->getJson('/api/v1/payables?search=Aluguer')
            ->assertOk()
            ->assertJsonFragment(['id' => $id]);

        $this->getJson("/api/v1/payables/{$id}")
            ->assertOk()
            ->assertJsonPath('data.notes', 'Mensalidade');

        $this->putJson("/api/v1/payables/{$id}", [
            'description' => 'Aluguer atualizado',
            'amount' => 300,
        ])->assertOk()
            ->assertJsonPath('data.description', 'Aluguer atualizado')
            ->assertJsonPath('data.amount', '300.00')
            ->assertJsonPath('data.status', 'pending');

        $this->deleteJson("/api/v1/payables/{$id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('payables', ['id' => $id]);
    }

    public function test_amount_must_be_positive(): void
    {
        Sanctum::actingAs($this->admin);

        $this->postJson('/api/v1/payables', [
            'description' => 'Inválido',
            'amount' => 0,
            'due_date' => now()->toDateString(),
            'type' => 'expense',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['amount']);

        $this->postJson('/api/v1/payables', [
            'description' => 'Inválido',
            'amount' => -10,
            'due_date' => now()->toDateString(),
            'type' => 'expense',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['amount']);
    }

    public function test_supplier_and_professional_can_be_nullable_or_valid(): void
    {
        Sanctum::actingAs($this->admin);

        $supplier = Supplier::factory()->create();
        $professional = Professional::factory()->create();

        $without = $this->postJson('/api/v1/payables', [
            'description' => 'Despesa genérica',
            'amount' => 40,
            'due_date' => now()->addDay()->toDateString(),
            'type' => 'other',
            'supplier_id' => null,
            'professional_id' => null,
        ]);

        $without->assertCreated()
            ->assertJsonPath('data.supplier_id', null)
            ->assertJsonPath('data.professional_id', null);

        $withSupplier = $this->postJson('/api/v1/payables', [
            'description' => 'Compra a fornecedor',
            'amount' => 80,
            'due_date' => now()->addDays(5)->toDateString(),
            'type' => 'supplier',
            'supplier_id' => $supplier->id,
        ]);

        $withSupplier->assertCreated()
            ->assertJsonPath('data.supplier_id', $supplier->id)
            ->assertJsonPath('data.supplier.name', $supplier->name);

        $withProfessional = $this->postJson('/api/v1/payables', [
            'description' => 'Comissão mensal',
            'amount' => 120,
            'due_date' => now()->addDays(7)->toDateString(),
            'type' => 'commission',
            'professional_id' => $professional->id,
        ]);

        $withProfessional->assertCreated()
            ->assertJsonPath('data.professional_id', $professional->id)
            ->assertJsonPath('data.professional.name', $professional->name);

        $this->postJson('/api/v1/payables', [
            'description' => 'Fornecedor inexistente',
            'amount' => 10,
            'due_date' => now()->toDateString(),
            'type' => 'supplier',
            'supplier_id' => 99999,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['supplier_id']);
    }

    public function test_inactive_supplier_can_remain_associated_on_existing_payable(): void
    {
        Sanctum::actingAs($this->admin);

        $supplier = Supplier::factory()->create(['is_active' => true]);
        $payable = Payable::factory()->forSupplier($supplier)->create();

        $supplier->update(['is_active' => false]);

        $this->getJson("/api/v1/payables/{$payable->id}")
            ->assertOk()
            ->assertJsonPath('data.supplier_id', $supplier->id)
            ->assertJsonPath('data.supplier.is_active', false);
    }

    public function test_mark_as_paid_sets_paid_at_and_status(): void
    {
        Sanctum::actingAs($this->admin);

        $payable = Payable::factory()->create([
            'created_by' => $this->admin->id,
        ]);

        $paidAt = now()->toDateString();

        $this->postJson("/api/v1/payables/{$payable->id}/pay", [
            'paid_at' => $paidAt,
        ])->assertOk()
            ->assertJsonPath('data.status', 'paid')
            ->assertJsonPath('data.is_paid', true)
            ->assertJsonPath('data.paid_at', $paidAt)
            ->assertJsonPath('data.paid_by', $this->admin->id);

        $this->postJson("/api/v1/payables/{$payable->id}/pay")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['payable']);
    }

    public function test_mark_as_paid_defaults_paid_at_to_today(): void
    {
        Sanctum::actingAs($this->admin);

        $payable = Payable::factory()->create();

        $this->postJson("/api/v1/payables/{$payable->id}/pay")
            ->assertOk()
            ->assertJsonPath('data.status', 'paid')
            ->assertJsonPath('data.paid_at', now()->toDateString());
    }

    public function test_overdue_is_computed_for_pending_past_due_date(): void
    {
        Sanctum::actingAs($this->admin);

        $overdue = Payable::factory()->overdue()->create();
        $pending = Payable::factory()->create([
            'due_date' => now()->addDays(2)->toDateString(),
        ]);
        $paid = Payable::factory()->paid()->create([
            'due_date' => now()->subDays(5)->toDateString(),
        ]);

        $this->getJson("/api/v1/payables/{$overdue->id}")
            ->assertOk()
            ->assertJsonPath('data.is_overdue', true)
            ->assertJsonPath('data.display_status', 'overdue')
            ->assertJsonPath('data.display_status_label', 'Vencido')
            ->assertJsonPath('data.status', 'pending');

        $this->getJson('/api/v1/payables?status=overdue')
            ->assertOk()
            ->assertJsonFragment(['id' => $overdue->id])
            ->assertJsonMissing(['id' => $pending->id])
            ->assertJsonMissing(['id' => $paid->id]);

        $this->getJson('/api/v1/payables?status=pending')
            ->assertOk()
            ->assertJsonFragment(['id' => $overdue->id])
            ->assertJsonFragment(['id' => $pending->id]);
    }

    public function test_filters_by_type_supplier_professional_and_due_range(): void
    {
        Sanctum::actingAs($this->admin);

        $supplier = Supplier::factory()->create();
        $professional = Professional::factory()->create();

        $match = Payable::factory()->create([
            'type' => PayableType::Supplier,
            'supplier_id' => $supplier->id,
            'due_date' => now()->addDays(3)->toDateString(),
            'description' => 'Filtro match',
        ]);

        Payable::factory()->forCommission($professional)->create([
            'due_date' => now()->addDays(20)->toDateString(),
        ]);

        $this->getJson('/api/v1/payables?type=supplier&supplier_id='.$supplier->id)
            ->assertOk()
            ->assertJsonFragment(['id' => $match->id]);

        $this->getJson('/api/v1/payables?professional_id='.$professional->id)
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $from = now()->addDays(1)->toDateString();
        $to = now()->addDays(5)->toDateString();

        $this->getJson("/api/v1/payables?due_from={$from}&due_to={$to}")
            ->assertOk()
            ->assertJsonFragment(['id' => $match->id]);
    }

    public function test_can_delete_paid_payable(): void
    {
        Sanctum::actingAs($this->admin);

        $payable = Payable::factory()->paid()->create();

        $this->deleteJson("/api/v1/payables/{$payable->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('payables', ['id' => $payable->id]);
    }

    public function test_update_does_not_accept_audit_or_status_fields(): void
    {
        Sanctum::actingAs($this->admin);

        $payable = Payable::factory()->create([
            'status' => PayableStatus::Pending,
            'created_by' => $this->admin->id,
        ]);

        $this->putJson("/api/v1/payables/{$payable->id}", [
            'description' => 'Sem alterar estado',
            'status' => 'paid',
            'paid_at' => now()->toDateString(),
            'created_by' => 999,
            'paid_by' => 999,
        ])->assertOk()
            ->assertJsonPath('data.description', 'Sem alterar estado')
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.paid_at', null)
            ->assertJsonPath('data.created_by', $this->admin->id)
            ->assertJsonPath('data.paid_by', null);
    }

    public function test_professional_and_client_cannot_manage_payables(): void
    {
        $payable = Payable::factory()->create();

        Sanctum::actingAs($this->professional);
        $this->getJson('/api/v1/payables')->assertForbidden();
        $this->postJson('/api/v1/payables', [
            'description' => 'X',
            'amount' => 10,
            'due_date' => now()->toDateString(),
            'type' => 'expense',
        ])->assertForbidden();
        $this->postJson("/api/v1/payables/{$payable->id}/pay")->assertForbidden();

        Sanctum::actingAs($this->client);
        $this->getJson('/api/v1/payables')->assertForbidden();
        $this->putJson("/api/v1/payables/{$payable->id}", [
            'description' => 'Y',
        ])->assertForbidden();
        $this->deleteJson("/api/v1/payables/{$payable->id}")->assertForbidden();
    }
}
