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
        // A dealer prepares a customer's whole account, then hands them a WW ID
        // and emails a code. The customer redeems the two to claim the account.
        Schema::create('handovers', function (Blueprint $table) {
            $table->id();
            $table->string('ww_id')->unique();
            $table->string('code_hash');
            $table->foreignId('dealer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
            $table->json('cover')->nullable();      // selected packages, captured as entered
            $table->decimal('monthly_price', 8, 2)->default(0);
            $table->decimal('commission', 8, 2)->default(0);
            $table->timestamp('claimed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('handovers');
    }
};
