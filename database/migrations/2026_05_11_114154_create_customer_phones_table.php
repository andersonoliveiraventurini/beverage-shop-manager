<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_phones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('number', 32);
            $table->string('label', 40)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->index(['customer_id', 'is_primary']);
            $table->index('number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_phones');
    }
};
