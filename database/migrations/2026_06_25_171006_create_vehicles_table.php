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
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('registration');

            // From the reg lookup.
            $table->string('make')->nullable();
            $table->string('model')->nullable();
            $table->string('derivative')->nullable();
            $table->unsignedSmallInteger('year')->nullable();
            $table->string('fuel')->nullable();
            $table->string('colour')->nullable();
            $table->unsignedInteger('engine_capacity')->nullable();
            $table->unsignedInteger('mileage')->nullable();
            $table->date('mot_due')->nullable();
            $table->date('tax_due')->nullable();
            $table->json('mot_history')->nullable();

            // Captured at handover or self-onboard.
            $table->date('insurance_renewal')->nullable();
            $table->date('last_service')->nullable();
            $table->date('service_due')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
