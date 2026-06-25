<?php

namespace App\Enums;

// The cover level on a warranty agreement. Matches the tiers in the iOS app.
enum WarrantyTier: string
{
    case Platinum = 'platinum';
    case Gold = 'gold';
    case Silver = 'silver';
}
