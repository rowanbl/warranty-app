<?php

use Illuminate\Support\Facades\Route;

// Single-page app: every non-API request returns the frontend shell and
// lets the client-side router take over. API requests are handled in
// routes/api.php under the /api prefix.
Route::view('/{any?}', 'app')->where('any', '^(?!api|up).*$');
