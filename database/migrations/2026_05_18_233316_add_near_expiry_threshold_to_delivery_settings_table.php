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
            // Days of buffer the dashboard considers "near expiry". Default 30
            // covers the PRD F06 requirement ("near-expiry alert default 30 days").
            $table->unsignedSmallInteger('near_expiry_threshold_days')->default(30)->after('track_water_shells');
        });
    }

    public function down(): void
    {
        Schema::table('delivery_settings', function (Blueprint $table) {
            $table->dropColumn('near_expiry_threshold_days');
        });
    }
};
