<?php

declare(strict_types=1);

namespace Tilttek\Quiz\Event;

/**
 * Dispatched when the host shows the next question of a live session: the players' screens read it and start its
 * timer. It carries identifiers and the position of the question only; the listener reads the rest from the session.
 */
final readonly class QuestionShown
{
    public function __construct(
        public string $quiz,
        public string $session,
        public int $position,
    ) {
    }
}
