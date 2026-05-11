<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->string('sku', 64)->unique();
            $table->string('size', 80);
            $table->boolean('is_returnable')->default(false);
            $table->decimal('shell_cost', 10, 2)->nullable();
            $table->decimal('sale_price', 10, 2);
            $table->decimal('cost_price', 10, 2)->nullable();
            $table->unsignedInteger('min_stock')->default(5);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
