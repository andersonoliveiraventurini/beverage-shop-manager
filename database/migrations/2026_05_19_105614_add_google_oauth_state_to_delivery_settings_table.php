<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('delivery_settings', function (Blueprint $table) {
            // Tokens for the dedicated FA Google account. Filled by the OAuth
            // grant flow described in docs/RUNBOOK_GOOGLE.md.
            $table->text('google_access_token')->nullable();
            $table->text('google_refresh_token')->nullable();
            $table->timestamp('google_token_expires_at')->nullable();
            $table->string('google_drive_folder_id', 80)->nullable();
            $table->string('google_contacts_sync_token', 240)->nullable();
            $table->timestamp('google_contacts_synced_at')->nullable();
            $table->boolean('google_contacts_sync_paused')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('delivery_settings', function (Blueprint $table) {
            $table->dropColumn([
                'google_access_token',
                'google_refresh_token',
                'google_token_expires_at',
                'google_drive_folder_id',
                'google_contacts_sync_token',
                'google_contacts_synced_at',
                'google_contacts_sync_paused',
            ]);
        });
    }
};
