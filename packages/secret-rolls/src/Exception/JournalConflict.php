<?php

declare(strict_types=1);

namespace Tilttek\SecretRolls\Exception;

use RuntimeException;

/**
 * Two writers appended to the journal of a scene at once: the entry does not come at the position that follows the
 * last one. The caller reads the journal again and retries.
 */
final class JournalConflict extends RuntimeException implements SecretRollsException
{
    public static function at(string $scene, int $position, int $expected): self
    {
        return new self(\sprintf('The journal of the scene %s expects position %d; an entry came at position %d.', $scene, $expected, $position));
    }
}
