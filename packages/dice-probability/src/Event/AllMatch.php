<?php

declare(strict_types=1);

namespace Tilttek\DiceProbability\Event;

use Override;
use Tilttek\Dice\Expression\Validator;

/**
 * Every die shows a value that matches: all above 3 with `new Comparison(Comparator::Greater, 3)`.
 */
final readonly class AllMatch implements Event
{
    public function __construct(
        public Validator $faces,
    ) {
    }

    #[Override]
    public function matches(Faces $faces): bool
    {
        return $faces->matching($this->faces) === $faces->dice();
    }
}
