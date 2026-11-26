<?php

declare(strict_types=1);

namespace Tilttek\CheckInRewards\Contract;

use Phil\Checkin\CheckIn;
use Tilttek\CheckInRewards\Presence;

/**
 * What the application knows of an admission and the library does not: whose credential was scanned, and what the
 * checkpoint stands for. It reads the check-in as the ledger keeps it, the only record the library trusts.
 *
 * It returns null when the admission rewards no one: a credential of no member, a member who left or was erased, a
 * checkpoint that is not part of any event.
 */
interface PresenceResolver
{
    public function resolve(CheckIn $checkIn): ?Presence;
}
