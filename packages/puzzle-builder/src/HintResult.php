<?php

declare(strict_types=1);

namespace Tilttek\PuzzleBuilder;

/**
 * The hint revealed, its tier, how many are left, and the progress to store after it.
 */
final readonly class HintResult
{
    /**
     * @param int $tier from 1, the first hint of the puzzle
     * @param int $left the tiers still hidden after this one
     */
    public function __construct(
        public Hint $hint,
        public int $tier,
        public int $left,
        public Progress $progress,
    ) {
    }
}
