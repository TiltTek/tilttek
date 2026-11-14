<?php

declare(strict_types=1);

namespace Tilttek\Quiz\Event;

/**
 * Dispatched when the host closes the question shown in a live session: the screens show its answer and the standings.
 * It carries identifiers and the position of the question only.
 */
final readonly class QuestionClosed
{
    public function __construct(
        public string $quiz,
        public string $session,
        public int $position,
    ) {
    }
}
