<?php

namespace App\Console\Commands;

use App\Services\Vehicle\VehicleLookupService;
use Illuminate\Console\Attributes\Argument;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('ww:lookup')]
#[Description('Look a registration up against the real DVSA and DVLA APIs and print what comes back. Handy for confirming the live lookup works.')]
class LookupVehicle extends Command
{
    #[Argument('The registration to look up, e.g. LV68KXR')]
    public string $registration;

    public function handle(VehicleLookupService $lookup): int
    {
        $vehicle = $lookup->lookup($this->registration);

        if ($vehicle === null) {
            $this->error("No vehicle found for {$this->registration} (or the lookup failed).");

            return self::FAILURE;
        }

        $data = $vehicle->toArray();
        $history = $data['mot_history'];
        unset($data['mot_history']);

        $this->table(['Field', 'Value'], collect($data)->map(fn ($v, $k) => [$k, is_null($v) ? '—' : $v])->values());

        $this->newLine();
        $this->info(count($history).' MOT record(s):');
        $this->table(
            ['Date', 'Result', 'Mileage', 'Expiry'],
            array_map(fn ($r) => [$r['date'], $r['result'], $r['mileage'], $r['expiry']], $history),
        );

        return self::SUCCESS;
    }
}
