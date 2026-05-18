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
        Schema::create('stores', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('street', 180);
            $table->string('number', 20)->nullable();
            $table->string('complement', 120)->nullable();
            $table->string('district', 80);
            $table->string('city', 80)->default('Campinas');
            $table->string('state', 2)->default('SP');
            $table->string('zip', 16)->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->string('phone_landline', 32)->nullable();
            $table->string('phone_mobile', 32)->nullable();
            $table->string('whatsapp', 32)->nullable();
            $table->string('hours', 120)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stores');
    }
};
