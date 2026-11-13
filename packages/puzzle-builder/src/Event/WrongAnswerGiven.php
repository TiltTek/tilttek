<?php

declare(strict_types=1);

namespace Tilttek\PuzzleBuilder\Event;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;

/**
 * A guess that is neither the answer nor a step. The guess itself is not carried. Dispatched once the move is made; it names identifiers only.
 */
final readonly class WrongAnswerGiven
{
    public function __construct(
        public string $scenario,
        public ResourceReference $team,
        public string $puzzle,
        public DateTimeImmutable $at,
    ) {
    }
}
