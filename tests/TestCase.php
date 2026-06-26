<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Routes return the Preact shell (app.blade.php), which calls @vite. CI
        // doesn't build the frontend for the PHP job, so without this the missing
        // manifest 500s every page render. Tests don't care about real assets.
        $this->withoutVite();
    }
}
