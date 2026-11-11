<?php

declare(strict_types=1);

namespace Tilttek\Badges\Exception;

use DomainException;

/**
 * A badge the member has not earned, asked to be shown, featured, worn as a title, shared or issued.
 */
final class NotEarned extends DomainException implements BadgesException
{
    public static function badge(string $code, string $member): self
    {
        return new self(\sprintf('%s has not earned the badge "%s".', $member, $code));
    }
}
