<?php

declare(strict_types=1);

namespace Tilttek\Quiz\Event;

/**
 * Dispatched after `QuizCompleted` when every answer of the run was fully right and in time: the moment for fireworks
 * rather than confetti, or for a badge. At one's own pace, it is dispatched once per quiz and member by default, for
 * the play that counts; `practice` is true only when the application asked for every perfect play. It carries
 * identifiers only.
 */
final readonly class PerfectScore
{
    public function __construct(
        public string $quiz,
        public string $run,
        public string $player,
        public bool $practice = false,
    ) {
    }
}
