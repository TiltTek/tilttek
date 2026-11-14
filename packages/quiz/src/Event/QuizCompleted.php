<?php

declare(strict_types=1);

namespace Tilttek\Quiz\Event;

/**
 * Dispatched once a player's run through a quiz is over: the end of a play at their own pace, or the end of a live
 * session for each of its players. The application shows the result, awards points or plays a celebration. It carries
 * identifiers only: `run` is the play or the live session, `player` the member's reference or the guest's identifier;
 * the listener reads the rest. `practice` is true for a play at one's own pace that does not count, the member having
 * played the quiz before: a listener that awards points or badges leaves it out.
 *
 * It is dispatched at least once: a listener is idempotent, keyed by `key()`.
 */
final readonly class QuizCompleted
{
    public function __construct(
        public string $quiz,
        public string $run,
        public string $player,
        public bool $perfect,
        public bool $practice = false,
    ) {
    }

    /** The same each time this player's run is dispatched again: a run is completed once. */
    public function key(): string
    {
        return \sprintf('%s/%s/%s/completed', $this->quiz, $this->run, $this->player);
    }
}
