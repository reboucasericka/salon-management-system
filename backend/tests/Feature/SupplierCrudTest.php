<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SupplierCrudTest extends TestCase
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
            'last_name' => 'Supplier',
            'email' => 'admin-supplier@test.com',
            'role' => UserRole::Admin,
            'is_active' => true,
            'password' => Hash::make('password'),
        ]);

        $this->professional = User::create([
            'first_name' => 'Pro',
            'last_name' => 'Supplier',
            'email' => 'pro-supplier@test.com',
            'role' => UserRole::Professional,
            'is_active' => true,
            'password' => Hash::make('password'),
        ]);

        $this->client = User::create([
            'first_name' => 'Client',
            'last_name' => 'Supplier',
            'email' => 'client-supplier@test.com',
            'role' => UserRole::Client,
            'is_active' => true,
            'password' => Hash::make('password'),
        ]);
    }

    public function test_admin_can_list_create_show_update_and_deactivate_supplier(): void
    {
        Sanctum::actingAs($this->admin);

        $create = $this->postJson('/api/v1/suppliers', [
            'name' => 'Distribuidora Beleza',
            'phone' => '+351 912 345 678',
            'email' => 'contacto@beleza.pt',
            'tax_id' => '123456789',
            'address' => 'Rua das Flores 10',
            'delivery_time_days' => 5,
            'notes' => 'Entrega às terças',
        ]);

        $create->assertCreated()
            ->assertJsonPath('data.name', 'Distribuidora Beleza')
            ->assertJsonPath('data.phone', '+351 912 345 678')
            ->assertJsonPath('data.tax_id', '123456789')
            ->assertJsonPath('data.delivery_time_days', 5)
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.products_count', 0);

        $id = $create->json('data.id');

        $this->getJson('/api/v1/suppliers?search=Beleza')
            ->assertOk()
            ->assertJsonFragment(['id' => $id]);

        $this->getJson("/api/v1/suppliers/{$id}")
            ->assertOk()
            ->assertJsonPath('data.email', 'contacto@beleza.pt');

        $this->putJson("/api/v1/suppliers/{$id}", [
            'name' => 'Distribuidora Beleza Lda',
            'is_active' => false,
        ])->assertOk()
            ->assertJsonPath('data.name', 'Distribuidora Beleza Lda')
            ->assertJsonPath('data.is_active', false);

        $this->getJson('/api/v1/suppliers?is_active=0')
            ->assertOk()
            ->assertJsonFragment(['id' => $id]);
    }

    public function test_admin_can_delete_supplier_without_products(): void
    {
        Sanctum::actingAs($this->admin);

        $supplier = Supplier::factory()->create();

        $this->deleteJson("/api/v1/suppliers/{$supplier->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('suppliers', ['id' => $supplier->id]);
    }

    public function test_delete_is_blocked_when_supplier_has_products(): void
    {
        Sanctum::actingAs($this->admin);

        $supplier = Supplier::factory()->create();
        Product::factory()->create(['supplier_id' => $supplier->id]);

        $this->deleteJson("/api/v1/suppliers/{$supplier->id}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['supplier']);

        $this->assertDatabaseHas('suppliers', ['id' => $supplier->id]);
    }

    public function test_validation_errors_on_create(): void
    {
        Sanctum::actingAs($this->admin);

        $this->postJson('/api/v1/suppliers', [
            'name' => '',
            'phone' => '',
            'email' => 'not-an-email',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'phone', 'email']);
    }

    public function test_professional_and_client_cannot_manage_suppliers(): void
    {
        $supplier = Supplier::factory()->create();

        Sanctum::actingAs($this->professional);
        $this->getJson('/api/v1/suppliers')->assertForbidden();
        $this->postJson('/api/v1/suppliers', [
            'name' => 'X',
            'phone' => '123',
        ])->assertForbidden();

        Sanctum::actingAs($this->client);
        $this->getJson('/api/v1/suppliers')->assertForbidden();
        $this->putJson("/api/v1/suppliers/{$supplier->id}", [
            'name' => 'Y',
        ])->assertForbidden();
        $this->deleteJson("/api/v1/suppliers/{$supplier->id}")->assertForbidden();
    }

    public function test_product_can_have_nullable_or_assigned_supplier(): void
    {
        Sanctum::actingAs($this->admin);

        $active = Supplier::factory()->create(['is_active' => true]);
        $inactive = Supplier::factory()->inactive()->create();

        $without = $this->postJson('/api/v1/products', [
            'name' => 'Shampoo Sem Fornecedor',
            'sale_price' => 10,
            'supplier_id' => null,
        ]);

        $without->assertCreated()
            ->assertJsonPath('data.supplier_id', null);

        $with = $this->postJson('/api/v1/products', [
            'name' => 'Shampoo Com Fornecedor',
            'sale_price' => 12,
            'supplier_id' => $active->id,
        ]);

        $with->assertCreated()
            ->assertJsonPath('data.supplier_id', $active->id)
            ->assertJsonPath('data.supplier.name', $active->name);

        $this->postJson('/api/v1/products', [
            'name' => 'Shampoo Inativo',
            'sale_price' => 8,
            'supplier_id' => $inactive->id,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['supplier_id']);

        $existing = Product::factory()->create(['supplier_id' => null]);
        $this->assertNull($existing->fresh()->supplier_id);

        $this->getJson('/api/v1/products?supplier_id='.$active->id)
            ->assertOk()
            ->assertJsonFragment(['id' => $with->json('data.id')]);

        $this->getJson('/api/v1/suppliers?is_active=1')
            ->assertOk()
            ->assertJsonFragment(['id' => $active->id])
            ->assertJsonMissing(['id' => $inactive->id, 'name' => $inactive->name]);
    }

    public function test_update_product_can_clear_supplier(): void
    {
        Sanctum::actingAs($this->admin);

        $supplier = Supplier::factory()->create();
        $product = Product::factory()->create(['supplier_id' => $supplier->id]);

        $this->putJson("/api/v1/products/{$product->id}", [
            'supplier_id' => null,
        ])->assertOk()
            ->assertJsonPath('data.supplier_id', null);
    }
}
