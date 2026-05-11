<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('address_id')->nullable()->constrained('customer_addresses')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('type', 16)->default('counter');
            $table->string('payment_method', 16);
            $table->string('status', 16)->default('open');

            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('delivery_fee', 8, 2)->default(0);
            $table->decimal('building_fee', 8, 2)->default(0);
            $table->decimal('out_of_area_override', 8, 2)->nullable();
            $table->decimal('card_fee', 8, 2)->default(0);
            $table->decimal('discount', 8, 2)->default(0);
            $table->string('discount_reason', 180)->nullable();
            $table->decimal('total', 10, 2)->default(0);

            $table->boolean('contains_water')->default(false);
            $table->text('notes')->nullable();
            $table->timestamp('paid_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['customer_id', 'created_at']);
            $table->index(['status', 'created_at']);
            $table->index('contains_water');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
