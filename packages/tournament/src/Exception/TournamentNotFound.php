<?php

declare(strict_types=1);

namespace Tilttek\Tournament\Exception;

use RuntimeException;

/**
 * No tournament has this identifier in the store.
 */
final class TournamentNotFound extends RuntimeException implements TournamentException
{
    public static function withId(string $id): self
    {
        return new self(\sprintf('No tournament has the identifier %s.', $id));
    }
}
