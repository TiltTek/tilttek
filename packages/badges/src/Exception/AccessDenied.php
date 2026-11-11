<?php

declare(strict_types=1);

namespace Tilttek\Badges\Exception;

use RuntimeException;

/**
 * Another application asked for a member's badges without the member's consent, or after the member withdrew it.
 */
final class AccessDenied extends RuntimeException implements BadgesException
{
    public static function to(string $client, string $member): self
    {
        return new self(\sprintf('%s has not allowed "%s" to read their badges.', $member, $client));
    }
}
