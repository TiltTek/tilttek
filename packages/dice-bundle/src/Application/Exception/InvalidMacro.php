<?php

declare(strict_types=1);

namespace Tilttek\DiceBundle\Application\Exception;

use InvalidArgumentException;
use Tilttek\Dice\Exception\DiceException;
use Tilttek\DiceBundle\Domain\MacroLevel;

/**
 * A macro that cannot be defined: a name that is not one, or a level the shelves give no owner for.
 */
final class InvalidMacro extends InvalidArgumentException implements DiceException
{
    public static function named(string $name): self
    {
        return new self(\sprintf('A macro is named by a letter, then letters and digits, 32 at most; "%s" is not.', $name));
    }

    public static function withoutOwner(MacroLevel $level): self
    {
        return new self(\sprintf('The shelves give no owner for the level "%s".', $level->value));
    }
}
