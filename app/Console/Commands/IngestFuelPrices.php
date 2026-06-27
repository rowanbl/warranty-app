<?php

namespace App\Console\Commands;

use App\Services\Fuel\FuelIngestService;
use Illuminate\Console\Command;

class IngestFuelPrices extends Command
{
    protected $signature = 'fuel:ingest';

    protected $description = 'Pull the latest fuel prices from the Fuel Finder feed and geocode any new forecourts.';

    public function handle(FuelIngestService $ingest): int
    {
        $summary = $ingest->run();

        $this->info("Fuel ingest: {$summary['stations']} forecourts, {$summary['geocoded']} newly geocoded.");

        if ($summary['error'] !== null) {
            // Not a failure on its own (we keep whatever we already had), but
            // worth surfacing so a misconfigured feed is easy to spot.
            $this->warn('Feed note: '.$summary['error']);
        }

        return self::SUCCESS;
    }
}
