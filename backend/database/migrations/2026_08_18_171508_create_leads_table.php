<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the leads table.
     */
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();

            $table->string('type', 30);
            $table->string('status', 30)->default('pending');

            $table->string('name', 150);
            $table->string('email', 254);
            $table->string('company', 180);
            $table->string('company_registration', 80)->nullable();
            $table->string('phone', 50);

            $table->string('sector', 100)->nullable();
            $table->string('location', 180)->nullable();
            $table->string('quantity', 100)->nullable();
            $table->text('message')->nullable();

            $table->string('language', 5)->default('pt');
            $table->string('source_page', 50);

            $table->timestamps();

            $table->index(['type', 'status']);
            $table->index('created_at');
        });
    }

    /**
     * Remove the leads table.
     */
    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
