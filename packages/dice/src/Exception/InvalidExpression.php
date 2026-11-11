<?php

declare(strict_types=1);

namespace Tilttek\Dice\Exception;

use InvalidArgumentException;

/**
 * A dice expression that does not parse, with the offset of the character where the error was found and the reason,
 * which the message gives before the position.
 */
final class InvalidExpression extends InvalidArgumentException implements DiceException
{
    private function __construct(
        public readonly string $reason,
        public readonly string $input,
        public readonly int $position,
    ) {
        parent::__construct(\sprintf('%s at position %d of "%s".', $reason, $position, $input));
    }

    /**
     * @param int $position the offset, in characters from 0, of the error in the input
     */
    public static function at(string $input, int $position, string $reason): self
    {
        return new self($reason, $input, $position);
    }
}
