<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // account_type is the one type-aware column on users. It's the
            // discriminator that says which profile table to join. Everything
            // type-specific lives in that profile table, not here.
            $table->string('account_type')->default('customer')->after('email');

            // Customers can sign in with an email code and never set a password,
            // so it can't be required at the database level.
            $table->string('password')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('account_type');
            $table->string('password')->nullable(false)->change();
        });
    }
};
