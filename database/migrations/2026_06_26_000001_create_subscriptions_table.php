<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agreement_id')->constrained()->cascadeOnDelete();
            $table->string('type');                 // App\Enums\SubscriptionType
            $table->decimal('monthly_price', 8, 2)->default(0);
            $table->date('started_at');
            $table->date('ended_at')->nullable();   // null = still active
            $table->timestamps();

            // Quick "what's active for this agreement" lookups.
            $table->index(['agreement_id', 'ended_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
