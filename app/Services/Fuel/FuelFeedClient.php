<?php

namespace App\Services\Fuel;

use Generator;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * The live Fuel Finder feed (gov open data, Motor Fuel Price (Open Data)
 * Regulations 2025). OAuth client-credentials for a Bearer token, then the price
 * feed in pages of 500 forecourts. The feed has prices and a node id but no
 * location, so the ingest geocodes each forecourt separately.
 *
 * Everything degrades to "nothing" rather than throwing, so a feed outage during
 * a scheduled ingest is a no-op, not a crash. `lastError` says why, when asked.
 */
class FuelFeedClient
{
    // Why the last pull came back empty, if it did. Null on a clean run.
    public ?string $lastError = null;

    /**
     * Each page of the price feed as a list of raw forecourt records. Stops at
     * the first page that isn't a non-empty list, which is how the feed signals
     * the end (and how a maintenance page reads too).
     *
     * @return Generator<int, array<int, array<string, mixed>>>
     */
    public function priceBatches(): Generator
    {
        $token = $this->accessToken();

        if ($token === null) {
            return;
        }

        $maxBatches = (int) config('fuel.max_batches');

        for ($batch = 1; $batch <= $maxBatches; $batch++) {
            try {
                $response = Http::withToken($token)
                    ->acceptJson()
                    ->get(config('fuel.feed_url'), ['batch-number' => $batch]);
            } catch (ConnectionException $e) {
                $this->fail('feed host unreachable: '.$e->getMessage());

                return;
            }

            if (! $response->successful()) {
                $this->fail('feed call failed: HTTP '.$response->status().' '.Str::limit($response->body(), 300));

                return;
            }

            $stations = $response->json();

            // The feed ends with a non-list body (and a maintenance page decodes
            // to null), so that's our signal to stop paging.
            if (! is_array($stations) || $stations === [] || ! array_is_list($stations)) {
                return;
            }

            yield $stations;
        }
    }

    /**
     * Swap the client id + secret for a Bearer token. Null when the credentials
     * are missing, the host is unreachable, or the call fails. The token sits at
     * `data.access_token` in the response.
     */
    private function accessToken(): ?string
    {
        $clientId = config('fuel.client_id');
        $clientSecret = config('fuel.client_secret');

        if (empty($clientId) || empty($clientSecret)) {
            $this->fail('Fuel Finder credentials are not configured (client id/secret empty)');

            return null;
        }

        try {
            $response = Http::asForm()->post(config('fuel.token_url'), [
                'grant_type' => 'client_credentials',
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'scope' => config('fuel.scope'),
            ]);
        } catch (ConnectionException $e) {
            $this->fail('token host unreachable: '.$e->getMessage());

            return null;
        }

        if (! $response->successful()) {
            $this->fail('token call failed: HTTP '.$response->status().' '.Str::limit($response->body(), 300));

            return null;
        }

        $token = $response->json('data.access_token');

        if (empty($token)) {
            $this->fail('token call succeeded but returned no access_token');

            return null;
        }

        return $token;
    }

    private function fail(string $reason): void
    {
        $this->lastError = $reason;
        Log::warning('Fuel Finder feed: '.$reason);
    }
}
