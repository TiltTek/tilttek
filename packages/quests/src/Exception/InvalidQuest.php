<?php

declare(strict_types=1);

namespace Tilttek\Quests\Exception;

use InvalidArgumentException;

/**
 * A quest, a step, a reward, a schedule, a duel's key or a catalog is refused.
 */
final class InvalidQuest extends InvalidArgumentException implements QuestsException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }

    public static function name(string $what, string $name): self
    {
        return new self(\sprintf('"%s" is not a %s code: 1 to 64 lower-case letters, digits, "_", "." or "-", starting with a letter.', $name, $what));
    }
}
