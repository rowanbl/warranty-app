<?php

namespace App\Enums;

// Who the account belongs to. Drives where a user lands after login and what
// they're allowed to do. Customers mostly sign in with an email code, the
// rest (dealers, garages, staff) use a password.
enum AccountType: string
{
    case Customer = 'customer';
    case Dealer = 'dealer';
    case Garage = 'garage';
    case Admin = 'admin';

    // Types a person can pick when signing themselves up. Admin is seeded by
    // us, never self-assigned, so it's deliberately left out.
    public static function selfRegisterable(): array
    {
        return [self::Customer, self::Dealer, self::Garage];
    }
}
