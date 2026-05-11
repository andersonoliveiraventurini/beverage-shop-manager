<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 180);
            $table->string('document', 32)->nullable();
            $table->text('notes')->nullable();

            $table->boolean('in_delivery_area')->default(true);
            $table->decimal('distance_km', 6, 2)->nullable();
            $table->decimal('delivery_fee', 8, 2)->default(0);
            $table->decimal('building_fee', 8, 2)->default(0);
            $table->boolean('has_manual_fee_override')->default(false);
            $table->timestamp('fees_calculated_at')->nullable();

            $table->string('google_contact_id', 128)->nullable()->unique();
            $table->timestamp('google_synced_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('name');
            $table->index('document');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
