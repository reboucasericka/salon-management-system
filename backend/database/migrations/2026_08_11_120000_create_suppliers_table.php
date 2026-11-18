<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Campos migrados do legado (Supplier.cs):
 * - Name → name
 * - Phone → phone
 * - Email → email
 * - TaxId → tax_id
 * - Address → address
 * - DeliveryTime → delivery_time_days
 * - Notes → notes
 * - RegistrationDate → created_at (timestamps)
 *
 * Adicionado no Laravel (não existia no legado):
 * - is_active — permite desativar sem apagar quando há produtos associados
 *
 * Descartados (não existiam no legado): contact_name, city, postal_code, country
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('phone', 100);
            $table->string('email', 100)->nullable();
            $table->string('tax_id', 20)->nullable();
            $table->string('address', 200)->nullable();
            $table->unsignedInteger('delivery_time_days')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('name');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};
