<?php

namespace App\Services\Handover;

use App\Models\Handover;

// What a submitted handover gives back: the saved record plus the plaintext
// code, which the caller only needs for demos (it's already emailed).
readonly class HandoverResult
{
    public function __construct(public Handover $handover, public string $code) {}
}
