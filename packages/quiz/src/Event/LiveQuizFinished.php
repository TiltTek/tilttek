<?php

declare(strict_types=1);

namespace Tilttek\Quiz\Event;

/**
 * Dispatched once a live session is over, before the `QuizCompleted` of its players: the application shows the
 * podium. It carries identifiers only; the listener reads the standings from the session.
 */
final readonly class LiveQuizFinished
{
    public function __construct(
        public string $quiz,
        public string $session,
    ) {
    }
}
