<?php

declare(strict_types=1);

namespace Tilttek\Quiz\Event;

/**
 * Dispatched for each guest once a live session is over: the application offers them to become a member, and keeps
 * their score if they do, through `LiveQuizzes::claimGuest()`. It carries identifiers only, never the pseudonym.
 */
final readonly class GuestInvited
{
    public function __construct(
        public string $session,
        public string $guest,
    ) {
    }
}
