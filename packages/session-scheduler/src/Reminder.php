<?php

declare(strict_types=1);

namespace Tilttek\SessionScheduler;

use DateTimeImmutable;

/**
 * A reminder due for a session: the members to remind are the game master who runs it and the players who have not
 * said they are absent.
 */
final readonly class Reminder
{
    /**
     * @param list<string> $members
     */
    public function __construct(
        public string $series,
        public string $session,
        public DateTimeImmutable $startsAt,
        public DateTimeImmutable $dueAt,
        public array $members,
    ) {
    }
}
