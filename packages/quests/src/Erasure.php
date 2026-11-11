<?php

declare(strict_types=1);

namespace Tilttek\Quests;

/**
 * What erasing a member removed: their contributions, enrolments, completions and duels.
 */
final readonly class Erasure
{
    public function __construct(
        public int $contributions,
        public int $enrolments,
        public int $completions,
        public int $duels,
    ) {
    }
}
