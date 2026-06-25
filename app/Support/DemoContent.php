<?php

namespace App\Support;

/**
 * Loads the demo content the app used to ship in DemoData.json. It now lives on
 * the server (resources/demo/content.json) so the app reads it over the API.
 * Each dataset becomes real by replacing the matching endpoint, no app change.
 */
class DemoContent
{
    /**
     * @return array<string, mixed>
     */
    public static function all(): array
    {
        return json_decode(file_get_contents(resource_path('demo/content.json')), true);
    }

    public static function get(string $key): mixed
    {
        return static::all()[$key] ?? null;
    }
}
