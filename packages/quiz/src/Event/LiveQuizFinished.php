<?php

declare(strict_types=1);

namespace Tilttek\Quiz\Event;

/**
 * Dispatched once a live session is over, before the `QuizCompleted` of its players: the application shows the
 * podium. It carries identifiers only; the listener reads the standings from the session.
 *
 * It is dispatched at least once: a listener is idempotent, keyed by `key()`.
 */
final readonly class LiveQuizFinished
{
    public function __construct(
        public string $quiz,
        public string $session,
    ) {
    }

    /** The same each time the end of this session is dispatched again. */
    public function key(): string
    {
        return \sprintf('%s/finished', $this->session);
    }
}
