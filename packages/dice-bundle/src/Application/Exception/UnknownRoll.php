<?php

declare(strict_types=1);

namespace Tilttek\DiceBundle\Application\Exception;

use RuntimeException;
use Tilttek\Dice\Exception\DiceException;

/**
 * A replay asked for a roll the journal does not hold.
 */
final class UnknownRoll extends RuntimeException implements DiceException
{
    public static function withId(string $id): self
    {
        return new self(\sprintf('No recorded roll has the identifier "%s".', $id));
    }
}
