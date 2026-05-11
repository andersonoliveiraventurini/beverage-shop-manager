<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('label', 40)->nullable();
            $table->string('street', 180);
            $table->string('number', 20)->nullable();
            $table->string('complement', 120)->nullable();
            $table->string('district', 80)->nullable();
            $table->string('city', 80)->default('Campinas');
            $table->string('zip', 16)->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->boolean('is_building')->default(false);
            $table->boolean('is_primary')->default(false);
            $table->string('reference', 180)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['customer_id', 'is_primary']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_addresses');
    }
};
