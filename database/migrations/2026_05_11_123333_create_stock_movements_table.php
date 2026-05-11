<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('variant_id')->constrained('product_variants')->restrictOnDelete();

            // direction: 'in' (adds to stock) | 'out' (removes from stock)
            $table->string('direction', 8);
            // reason: 'sale', 'sale_reversal', 'manual_adjust', 'cargo', 'shell_in', 'shell_out'
            $table->string('reason', 32);

            $table->unsignedInteger('quantity');

            // Polymorphic source — e.g. a Sale, a SaleItem, a Cargo, manual.
            $table->string('source_type', 64)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();

            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['variant_id', 'created_at']);
            $table->index(['source_type', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
