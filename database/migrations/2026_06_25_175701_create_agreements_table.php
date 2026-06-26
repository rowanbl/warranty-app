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
        // A warranty agreement. It lives in its own table, linked to the account
        // (and the car it covers), so one account or email can hold several.
        Schema::create('agreements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_id')->nullable()->constrained()->nullOnDelete();
            // The address this agreement is for. The user may hold several.
            $table->foreignId('address_id')->nullable()->constrained()->nullOnDelete();
            $table->string('agreement_number')->unique();
            $table->string('tier');
            $table->string('status')->default('active');
            $table->date('start_date');
            $table->date('expiry_date');
            $table->unsignedInteger('claim_limit')->default(0);
            $table->decimal('monthly_price', 8, 2)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agreements');
    }
};
