<?php

declare(strict_types=1);

namespace Tilttek\CheckInRewards\Exception;

use RuntimeException;

/**
 * Someone asked for the attendance of another member: a member's attendance is read by that member only.
 */
final class AccessDenied extends RuntimeException implements CheckInRewardsException
{
    public static function toAttendanceOf(string $viewer, string $member): self
    {
        return new self(\sprintf('"%s" cannot read the attendance of "%s": a member\'s attendance is theirs alone.', $viewer, $member));
    }
}
