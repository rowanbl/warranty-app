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
        // Dealers and garages can't use the app until a human approves them.
        // Null means pending. Customers don't need this.
        foreach (['dealers', 'garages'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->timestamp('approved_at')->nullable()->after('business_name');
            });
        }
    }

    public function down(): void
    {
        foreach (['dealers', 'garages'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropColumn('approved_at');
            });
        }
    }
};
