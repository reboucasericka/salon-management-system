<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Enums\ReceivableStatus;
use App\Enums\SaleStatus;
use App\Enums\UserRole;
use App\Models\Client;
use App\Models\Professional;
use App\Models\Receivable;
use App\Models\Sale;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReceivableCrudTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $professionalUser;

    private User $clientUser;

    private Client $client;

    private Professional $professional;

    private Service $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'first_name' => 'Admin',
            'last_name' => 'Receivable',
            'email' => 'admin-receivable@test.com',
            'role' => UserRole::Admin,
            'is_active' => true,
            'password' => Hash::make('password'),
        ]);

        $this->professionalUser = User::create([
            'first_name' => 'Pro',
            'last_name' => 'Receivable',
            'email' => 'pro-receivable@test.com',
            'role' => UserRole::Professional,
            'is_active' => true,
            'password' => Hash::make('password'),
        ]);

        $this->clientUser = User::create([
            'first_name' => 'Client',
            'last_name' => 'Receivable',
            'email' => 'client-receivable@test.com',
            'role' => UserRole::Client,
            'is_active' => true,
            'password' => Hash::make('password'),
        ]);

        $this->client = Client::create([
            'name' => 'Maria Cliente',
            'email' => 'maria@test.com',
            'phone' => '912345678',
            'is_active' => true,
        ]);

        $this->professional = Professional::factory()->create([
            'name' => 'João Profissional',
        ]);

        $this->service = Service::create([
            'name' => 'Corte',
            'price' => 25,
            'duration_minutes' => 30,
            'is_active' => true,
        ]);
    }

    public function test_admin_can_crud_receivable(): void
    {
        Sanctum::actingAs($this->admin);

        $create = $this->postJson('/api/v1/receivables', [
            'description' => 'Pagamento de corte',
            'amount' => 25.50,
            'payment_method' => 'cash',
            'client_id' => $this->client->id,
            'professional_id' => $this->professional->id,
            'service_id' => $this->service->id,
        ]);

        $create->assertCreated()
            ->assertJsonPath('data.description', 'Pagamento de corte')
            ->assertJsonPath('data.amount', '25.50')
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.is_received', false)
            ->assertJsonPath('data.client_id', $this->client->id)
            ->assertJsonPath('data.professional_id', $this->professional->id)
            ->assertJsonPath('data.service_id', $this->service->id)
            ->assertJsonPath('data.created_by', $this->admin->id)
            ->assertJsonPath('data.reference_label', 'Corte');

        $id = $create->json('data.id');

        $this->getJson('/api/v1/receivables?search=corte')
            ->assertOk()
            ->assertJsonFragment(['id' => $id]);

        $this->getJson("/api/v1/receivables/{$id}")
            ->assertOk()
            ->assertJsonPath('data.client.name', 'Maria Cliente');

        $this->putJson("/api/v1/receivables/{$id}", [
            'description' => 'Pagamento atualizado',
            'amount' => 30,
        ])->assertOk()
            ->assertJsonPath('data.description', 'Pagamento atualizado')
            ->assertJsonPath('data.amount', '30.00')
            ->assertJsonPath('data.status', 'pending');

        $this->deleteJson("/api/v1/receivables/{$id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('receivables', ['id' => $id]);
    }

    public function test_amount_must_be_positive_and_required_fks_validated(): void
    {
        Sanctum::actingAs($this->admin);

        $this->postJson('/api/v1/receivables', [
            'description' => 'Inválido',
            'amount' => 0,
            'payment_method' => 'cash',
            'client_id' => $this->client->id,
            'professional_id' => $this->professional->id,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['amount']);

        $this->postJson('/api/v1/receivables', [
            'description' => 'Sem cliente',
            'amount' => 10,
            'payment_method' => 'card',
            'professional_id' => $this->professional->id,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['client_id']);

        $this->postJson('/api/v1/receivables', [
            'description' => 'Cliente inexistente',
            'amount' => 10,
            'payment_method' => 'cash',
            'client_id' => 99999,
            'professional_id' => $this->professional->id,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['client_id']);
    }

    public function test_service_and_sale_are_optional(): void
    {
        Sanctum::actingAs($this->admin);

        $sale = Sale::create([
            'client_id' => $this->client->id,
            'user_id' => $this->admin->id,
            'total_amount' => 50,
            'payment_method' => PaymentMethod::Card,
            'status' => SaleStatus::Paid,
        ]);

        $without = $this->postJson('/api/v1/receivables', [
            'description' => 'Recebimento avulso',
            'amount' => 40,
            'payment_method' => 'transfer',
            'client_id' => $this->client->id,
            'professional_id' => $this->professional->id,
            'service_id' => null,
            'sale_id' => null,
        ]);

        $without->assertCreated()
            ->assertJsonPath('data.service_id', null)
            ->assertJsonPath('data.sale_id', null);

        $withSale = $this->postJson('/api/v1/receivables', [
            'description' => 'Recebimento de venda',
            'amount' => 50,
            'payment_method' => 'card',
            'client_id' => $this->client->id,
            'professional_id' => $this->professional->id,
            'sale_id' => $sale->id,
        ]);

        $withSale->assertCreated()
            ->assertJsonPath('data.sale_id', $sale->id)
            ->assertJsonPath('data.reference_label', 'Venda #'.$sale->id);
    }

    public function test_inactive_client_remains_associated(): void
    {
        Sanctum::actingAs($this->admin);

        $receivable = Receivable::factory()
            ->forClient($this->client)
            ->forProfessional($this->professional)
            ->create();

        $this->client->update(['is_active' => false]);

        $this->getJson("/api/v1/receivables/{$receivable->id}")
            ->assertOk()
            ->assertJsonPath('data.client_id', $this->client->id)
            ->assertJsonPath('data.client.is_active', false);
    }

    public function test_mark_as_received_sets_received_at_and_received_by(): void
    {
        Sanctum::actingAs($this->admin);

        $receivable = Receivable::factory()
            ->forClient($this->client)
            ->forProfessional($this->professional)
            ->createdBy($this->admin)
            ->create();

        $receivedAt = now()->toDateString();

        $this->postJson("/api/v1/receivables/{$receivable->id}/receive", [
            'received_at' => $receivedAt,
        ])->assertOk()
            ->assertJsonPath('data.status', 'received')
            ->assertJsonPath('data.is_received', true)
            ->assertJsonPath('data.received_at', $receivedAt)
            ->assertJsonPath('data.received_by', $this->admin->id);

        $this->postJson("/api/v1/receivables/{$receivable->id}/receive")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['receivable']);
    }

    public function test_mark_as_received_defaults_received_at_to_today(): void
    {
        Sanctum::actingAs($this->admin);

        $receivable = Receivable::factory()
            ->forClient($this->client)
            ->forProfessional($this->professional)
            ->create();

        $this->postJson("/api/v1/receivables/{$receivable->id}/receive")
            ->assertOk()
            ->assertJsonPath('data.status', 'received')
            ->assertJsonPath('data.received_at', now()->toDateString());
    }

    public function test_overdue_uses_created_at_after_30_days(): void
    {
        Sanctum::actingAs($this->admin);

        $overdue = Receivable::factory()
            ->forClient($this->client)
            ->forProfessional($this->professional)
            ->overdue()
            ->create();

        $pending = Receivable::factory()
            ->forClient($this->client)
            ->forProfessional($this->professional)
            ->create();

        $received = Receivable::factory()
            ->forClient($this->client)
            ->forProfessional($this->professional)
            ->received()
            ->create([
                'created_at' => now()->subDays(40),
                'updated_at' => now()->subDays(40),
            ]);

        $this->getJson("/api/v1/receivables/{$overdue->id}")
            ->assertOk()
            ->assertJsonPath('data.is_overdue', true)
            ->assertJsonPath('data.display_status', 'overdue')
            ->assertJsonPath('data.display_status_label', 'Vencido')
            ->assertJsonPath('data.status', 'pending');

        $this->getJson('/api/v1/receivables?status=overdue')
            ->assertOk()
            ->assertJsonFragment(['id' => $overdue->id])
            ->assertJsonMissing(['id' => $pending->id])
            ->assertJsonMissing(['id' => $received->id]);
    }

    public function test_filters_by_client_professional_reference_and_created_range(): void
    {
        Sanctum::actingAs($this->admin);

        $otherClient = Client::create([
            'name' => 'Outro Cliente',
            'is_active' => true,
        ]);

        $match = Receivable::factory()
            ->forClient($this->client)
            ->forProfessional($this->professional)
            ->create([
                'service_id' => $this->service->id,
                'description' => 'Filtro match',
            ]);

        Receivable::factory()
            ->forClient($otherClient)
            ->forProfessional($this->professional)
            ->create();

        $this->getJson('/api/v1/receivables?client_id='.$this->client->id)
            ->assertOk()
            ->assertJsonFragment(['id' => $match->id]);

        $this->getJson('/api/v1/receivables?reference=service')
            ->assertOk()
            ->assertJsonFragment(['id' => $match->id]);

        $from = now()->subDay()->toDateString();
        $to = now()->addDay()->toDateString();

        $this->getJson("/api/v1/receivables?created_from={$from}&created_to={$to}")
            ->assertOk()
            ->assertJsonFragment(['id' => $match->id]);
    }

    public function test_can_delete_received_receivable(): void
    {
        Sanctum::actingAs($this->admin);

        $receivable = Receivable::factory()
            ->forClient($this->client)
            ->forProfessional($this->professional)
            ->received()
            ->create();

        $this->deleteJson("/api/v1/receivables/{$receivable->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('receivables', ['id' => $receivable->id]);
    }

    public function test_update_does_not_accept_audit_or_status_fields(): void
    {
        Sanctum::actingAs($this->admin);

        $receivable = Receivable::factory()
            ->forClient($this->client)
            ->forProfessional($this->professional)
            ->createdBy($this->admin)
            ->create([
                'status' => ReceivableStatus::Pending,
            ]);

        $this->putJson("/api/v1/receivables/{$receivable->id}", [
            'description' => 'Sem alterar estado',
            'status' => 'received',
            'received_at' => now()->toDateString(),
            'created_by' => 999,
            'received_by' => 999,
        ])->assertOk()
            ->assertJsonPath('data.description', 'Sem alterar estado')
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.received_at', null)
            ->assertJsonPath('data.created_by', $this->admin->id)
            ->assertJsonPath('data.received_by', null);
    }

    public function test_professional_and_client_cannot_manage_receivables(): void
    {
        $receivable = Receivable::factory()
            ->forClient($this->client)
            ->forProfessional($this->professional)
            ->create();

        Sanctum::actingAs($this->professionalUser);
        $this->getJson('/api/v1/receivables')->assertForbidden();
        $this->postJson('/api/v1/receivables', [
            'description' => 'X',
            'amount' => 10,
            'payment_method' => 'cash',
            'client_id' => $this->client->id,
            'professional_id' => $this->professional->id,
        ])->assertForbidden();
        $this->postJson("/api/v1/receivables/{$receivable->id}/receive")->assertForbidden();

        Sanctum::actingAs($this->clientUser);
        $this->getJson('/api/v1/receivables')->assertForbidden();
        $this->putJson("/api/v1/receivables/{$receivable->id}", [
            'description' => 'Y',
        ])->assertForbidden();
        $this->deleteJson("/api/v1/receivables/{$receivable->id}")->assertForbidden();
    }
}
