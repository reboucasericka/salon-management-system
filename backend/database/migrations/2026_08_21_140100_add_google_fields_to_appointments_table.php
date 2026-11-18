<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->string('google_event_id', 128)->nullable()->after('reminder_sent');
            $table->string('google_sync_status', 32)->nullable()->after('google_event_id');
            $table->timestamp('google_synced_at')->nullable()->after('google_sync_status');
            $table->string('google_sync_error', 500)->nullable()->after('google_synced_at');
            $table->index('google_sync_status');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropIndex(['google_sync_status']);
            $table->dropColumn([
                'google_event_id',
                'google_sync_status',
                'google_synced_at',
                'google_sync_error',
            ]);
        });
    }
};
