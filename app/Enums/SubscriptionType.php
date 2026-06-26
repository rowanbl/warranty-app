<?php

namespace App\Enums;

/**
 * The add-on subscriptions a customer can hold alongside their warranty. The
 * warranty itself is the agreement; these are the optional, rolling extras.
 * A fixed enum so each one is traceable in the subscriptions table.
 */
enum SubscriptionType: string
{
    case MotCover = 'mot_cover';
    case ServicingCover = 'servicing_cover';
    case ServicingStopCollection = 'servicing_stop_collection';

    /** The human label, which also matches the cover catalogue name. */
    public function label(): string
    {
        return match ($this) {
            self::MotCover => 'MOT Cover',
            self::ServicingCover => 'Servicing Cover',
            self::ServicingStopCollection => 'Servicing Stop collection',
        };
    }

    /** Resolve a catalogue cover-option name back to a type, or null. */
    public static function fromName(string $name): ?self
    {
        foreach (self::cases() as $case) {
            if (strcasecmp($case->label(), $name) === 0) {
                return $case;
            }
        }

        return null;
    }
}
