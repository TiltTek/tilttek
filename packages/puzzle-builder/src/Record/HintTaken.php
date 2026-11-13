<?php

declare(strict_types=1);

namespace Tilttek\PuzzleBuilder\Record;

use DateTimeImmutable;
use Tilttek\PuzzleBuilder\Instant;

/**
 * A tier of hints revealed: asked by the players, or pushed by the host.
 */
final readonly class HintTaken
{
    public DateTimeImmutable $at;

    /**
     * @param int $tier from 1, the first hint of the puzzle
     */
    public function __construct(
        public string $puzzle,
        public int $tier,
        DateTimeImmutable $at,
        public bool $pushed = false,
    ) {
        $this->at = Instant::utc($at);
    }
}
