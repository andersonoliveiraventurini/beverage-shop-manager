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
        Schema::create('backup_runs', function (Blueprint $table) {
            $table->id();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            // success | failed | partial
            $table->string('status', 16)->default('success');
            $table->string('file_name', 120)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->string('drive_file_id', 120)->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index('started_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('backup_runs');
    }
};
