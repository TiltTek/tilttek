<?php

declare(strict_types=1);

namespace Tilttek\GeneratorBundle\Application\Exception;

use InvalidArgumentException;
use Tilttek\GeneratorBundle\Application\MemoryTables;

/**
 * A key of a table with a memory that is empty or too long.
 */
final class InvalidStateKey extends InvalidArgumentException
{
    public static function of(string $key): self
    {
        return new self(\sprintf('The key "%s" of a state is a text of 1 to %d characters.', mb_substr($key, 0, 32), MemoryTables::KEY_LENGTH));
    }
}
