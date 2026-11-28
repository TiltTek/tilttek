<?php

declare(strict_types=1);

namespace Tilttek\PlayLog\Contract;

/**
 * Whether a member agreed beforehand that plays another member records are linked to them without asking: a friend,
 * a group they joined, a setting of their account. Without it, every seat of another member waits for their
 * confirmation.
 */
interface PlayerConsent
{
    public function allows(string $member, string $recordedBy): bool;
}
