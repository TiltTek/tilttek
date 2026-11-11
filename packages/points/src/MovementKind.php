<?php

declare(strict_types=1);

namespace Tilttek\Points;

/**
 * Why points moved. Three kinds are derived from the activity and the balances, and recomputed when a rule changes:
 * `Earned` by a rule, `Reversed` when the activity is cancelled, `Expired` when a credit outlives its currency's
 * lifetime. The others are decided by the application or by a person, and never recomputed: `Granted` and `Revoked`
 * by hand, `Spent` on a redemption and `Refunded` back, `Transferred` from one member to another.
 */
enum MovementKind: string
{
    case Earned = 'earned';

    case Reversed = 'reversed';

    case Expired = 'expired';

    case Granted = 'granted';

    case Revoked = 'revoked';

    case Spent = 'spent';

    case Refunded = 'refunded';

    case Transferred = 'transferred';

    /**
     * The kinds a rebuild forgets and recomputes.
     *
     * @return non-empty-list<self>
     */
    public static function derived(): array
    {
        return [self::Earned, self::Reversed, self::Expired];
    }

    public function isDerived(): bool
    {
        return \in_array($this, self::derived(), true);
    }
}
