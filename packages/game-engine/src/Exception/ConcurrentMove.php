<?php

declare(strict_types=1);

namespace Tilttek\GameEngine\Exception;

use RuntimeException;

/**
 * A change of a log that another change came before since it was read — a move appended or taken back —, or a log
 * that kept changing while it was read: the caller reads the table again.
 */
final class ConcurrentMove extends RuntimeException implements GameEngineException
{
    public static function at(string $gameId, int $expected, int $actual): self
    {
        return new self(\sprintf('The log of the table "%s" holds %d moves, not the %d expected: another move came first.', $gameId, $actual, $expected));
    }

    /**
     * A log of the number of moves the caller read, but at another revision: a move was taken back and another made.
     */
    public static function revised(string $gameId, int $expected, int $actual): self
    {
        return new self(\sprintf('The log of the table "%s" is at the revision %d, not the %d expected: a move was taken back since.', $gameId, $actual, $expected));
    }

    /**
     * A log that changed every time it was read.
     */
    public static function unsettled(string $gameId, int $reads): self
    {
        return new self(\sprintf('The log of the table "%s" changed during each of %d reads.', $gameId, $reads));
    }
}
