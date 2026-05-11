<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_settings', function (Blueprint $table) {
            $table->id();
            $table->decimal('radius_km', 6, 2)->default(2.00);
            $table->decimal('default_delivery_fee', 8, 2)->default(2.00);
            $table->decimal('out_of_area_extra_fee', 8, 2)->default(1.00);
            $table->decimal('default_building_fee', 8, 2)->default(1.00);
            $table->boolean('track_water_shells')->default(false);
            $table->timestamps();
        });

        // Seed the singleton row immediately so DeliverySetting::current() always finds it.
        DB::table('delivery_settings')->insert([
            'radius_km' => 2.00,
            'default_delivery_fee' => 2.00,
            'out_of_area_extra_fee' => 1.00,
            'default_building_fee' => 1.00,
            'track_water_shells' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_settings');
    }
};
