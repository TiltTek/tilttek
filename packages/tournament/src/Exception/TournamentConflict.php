<?php

declare(strict_types=1);

namespace Tilttek\Tournament\Exception;

use RuntimeException;

/**
 * The tournament changed since it was read: the change is not kept, and is made again on the tournament read anew.
 */
final class TournamentConflict extends RuntimeException implements TournamentException
{
    public static function changed(string $id): self
    {
        return new self(\sprintf('The tournament %s changed since it was read.', $id));
    }
}
