<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('salon_name', 100)->nullable();
            $table->string('timezone', 64)->nullable();
            $table->string('currency', 3)->default('EUR');
            $table->string('contact_email', 100)->nullable();
            $table->string('contact_phone', 30)->nullable();
            $table->string('address', 255)->nullable();
            $table->boolean('reminder_24h_enabled')->default(true);
            $table->boolean('reminder_2h_enabled')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
