<?php

declare(strict_types=1);

namespace Tilttek\Bounties\Exception;

use RuntimeException;

/**
 * Another writer changed the bounty first: the store refuses a version that is no longer the last one, and the
 * library tries three times before it gives up.
 */
final class BountyConflict extends RuntimeException implements BountiesException
{
    public static function exists(string $id): self
    {
        return new self(\sprintf('A bounty "%s" is stored already.', $id));
    }

    public static function subjectTaken(string $subject): self
    {
        return new self(\sprintf('An open bounty on %s is stored already.', $subject));
    }

    public static function stale(string $id, int $expected): self
    {
        return new self(\sprintf('The bounty "%s" is no longer at version %d.', $id, $expected));
    }

    public static function busy(string $id, int $attempts): self
    {
        return new self(\sprintf('The bounty "%s" changed under every one of %d attempts.', $id, $attempts));
    }
}
