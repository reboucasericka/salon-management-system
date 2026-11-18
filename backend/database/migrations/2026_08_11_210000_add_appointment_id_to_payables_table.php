<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payables', function (Blueprint $table) {
            $table->foreignId('appointment_id')
                ->nullable()
                ->after('professional_id')
                ->constrained()
                ->nullOnDelete();

            // Idempotência: no máximo um Payable por (tipo, appointment).
            // Vários NULL em appointment_id são permitidos (despesas sem origem).
            $table->unique(['type', 'appointment_id']);
        });
    }

    public function down(): void
    {
        Schema::table('payables', function (Blueprint $table) {
            $table->dropUnique(['type', 'appointment_id']);
            $table->dropConstrainedForeignId('appointment_id');
        });
    }
};
