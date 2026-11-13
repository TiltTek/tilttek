<?php

declare(strict_types=1);

namespace Tilttek\PuzzleBuilder\Record;

use DateTimeImmutable;
use Tilttek\PuzzleBuilder\Instant;

/**
 * A puzzle solved: by its answer, or skipped by the host for players who are stuck.
 */
final readonly class Solved
{
    public DateTimeImmutable $at;

    public function __construct(
        public string $puzzle,
        DateTimeImmutable $at,
        public bool $skipped = false,
    ) {
        $this->at = Instant::utc($at);
    }
}
