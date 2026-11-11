<?php

declare(strict_types=1);

namespace Tilttek\DiceProbability\Event;

use InvalidArgumentException;
use Override;

/**
 * Consecutive values shown, each by at least one die: a run of the given length, or of every die of the set when no
 * length is given — 1, 2, 3, 4, 5 on five dice. A run is two values at least: one die alone is no straight.
 */
final readonly class Straight implements Event
{
    /**
     * @throws InvalidArgumentException when the length is below 2
     */
    public function __construct(
        public ?int $length = null,
    ) {
        if (null !== $length && $length < 2) {
            throw new InvalidArgumentException(\sprintf('A straight runs over two values or more; %d is not.', $length));
        }
    }

    #[Override]
    public function matches(Faces $faces): bool
    {
        return $faces->longestRun() >= ($this->length ?? max(2, $faces->dice()));
    }
}
