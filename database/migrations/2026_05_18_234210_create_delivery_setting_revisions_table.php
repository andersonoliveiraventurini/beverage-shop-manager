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
        Schema::create('delivery_setting_revisions', function (Blueprint $table) {
            $table->id();
            $table->decimal('radius_km', 6, 2);
            $table->decimal('default_delivery_fee', 8, 2);
            $table->decimal('out_of_area_extra_fee', 8, 2);
            $table->decimal('default_building_fee', 8, 2);
            $table->boolean('track_water_shells')->default(false);
            $table->unsignedSmallInteger('near_expiry_threshold_days')->default(30);
            $table->unsignedInteger('customers_recomputed')->default(0);
            $table->unsignedInteger('customers_skipped')->default(0);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('delivery_setting_revisions');
    }
};
