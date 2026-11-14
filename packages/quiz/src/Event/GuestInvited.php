<?php

declare(strict_types=1);

namespace Tilttek\Quiz\Event;

/**
 * Dispatched for each guest once a live session is over: the application offers them to become a member, and keeps
 * their score if they do, through `LiveQuizzes::claimGuest()`. It carries identifiers only, never the pseudonym.
 *
 * It is dispatched at least once: a listener is idempotent, keyed by `key()`.
 */
final readonly class GuestInvited
{
    public function __construct(
        public string $session,
        public string $guest,
    ) {
    }

    /** The same each time this invitation is dispatched again. */
    public function key(): string
    {
        return \sprintf('%s/%s/invited', $this->session, $this->guest);
    }
}
