<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the lead delivery attempts table.
     */
    public function up(): void
    {
        Schema::create('lead_delivery_attempts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('lead_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->unsignedSmallInteger('attempt_number');
            $table->string('driver', 50);
            $table->string('status', 30);

            $table->unsignedSmallInteger('http_status')->nullable();
            $table->string('external_id', 191)->nullable();
            $table->string('error_code', 100)->nullable();
            $table->text('error_message')->nullable();

            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();

            $table->timestamps();

            $table->unique(['lead_id', 'attempt_number']);
            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Remove the lead delivery attempts table.
     */
    public function down(): void
    {
        Schema::dropIfExists('lead_delivery_attempts');
    }
};
