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
        // Null means pending. Customers don't need this. dateTime (not timestamp)
        // to avoid MySQL's timestamp range/precision quirks.
        foreach (['dealers', 'garages'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dateTime('approved_at')->nullable()->after('business_name');
            });
        }
    }

    public function down(): void
    {
        foreach (['dealers', 'garages'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropColumn('approved_at');
            });
        }
    }
};
