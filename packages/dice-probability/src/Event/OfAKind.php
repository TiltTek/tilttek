<?php

declare(strict_types=1);

namespace Tilttek\DiceProbability\Event;

use InvalidArgumentException;
use Override;

/**
 * At least so many dice show the same value, whatever it is: a double for two, a triple for three.
 */
final readonly class OfAKind implements Event
{
    /**
     * @throws InvalidArgumentException when fewer than two dice are asked for
     */
    public function __construct(
        public int $dice,
    ) {
        if ($dice < 2) {
            throw new InvalidArgumentException(\sprintf('Dice of a kind are two or more; %d is not.', $dice));
        }
    }

    public static function double(): self
    {
        return new self(2);
    }

    public static function triple(): self
    {
        return new self(3);
    }

    #[Override]
    public function matches(Faces $faces): bool
    {
        return $faces->mostOfAKind() >= $this->dice;
    }
}
