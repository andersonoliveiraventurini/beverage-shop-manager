<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('variant_id')->constrained('product_variants')->restrictOnDelete();

            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('unit_price', 10, 2);
            $table->decimal('line_total', 10, 2)->default(0);

            // Returnable-gallon modality + shell-validity snapshot.
            // modality = 'full'        → customer takes a filled gallon + shell (only delivered shell validity).
            // modality = 'exchange'    → customer returns an empty shell and takes a filled one (both validities).
            // modality = 'shell_only'  → customer takes an empty shell (only delivered shell validity).
            $table->string('modality', 16)->nullable();
            $table->date('returned_shell_expires_at')->nullable();
            $table->date('delivered_shell_expires_at')->nullable();

            $table->timestamps();

            $table->index('sale_id');
            $table->index('variant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_items');
    }
};
