<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fuel_stations', function (Blueprint $table) {
            $table->id();
            // The feed's stable id for a forecourt. Prices update against it.
            $table->string('node_id')->unique();
            $table->string('trading_name');
            $table->string('phone')->nullable();

            // Filled in by geocoding the trading name, since the price feed
            // carries no location of its own.
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('postcode')->nullable();
            $table->timestamp('geocoded_at')->nullable();
            $table->boolean('geocode_failed')->default(false);

            // Prices keyed by the feed's grade codes (E5, E10, B7_STANDARD,
            // B7_PREMIUM), refreshed every ingest.
            $table->json('prices')->nullable();
            $table->timestamp('prices_updated_at')->nullable();

            $table->timestamps();

            // Bounding-box lookups for "near me" search.
            $table->index(['latitude', 'longitude']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fuel_stations');
    }
};
