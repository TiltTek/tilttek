<?php

declare(strict_types=1);

namespace Tilttek\Activity;

use DateTimeImmutable;
use Tilttek\Activity\Exception\InvalidActivity;

/**
 * One line of the journal: its position, which only grows, what it says, the activity it is about — the cancelled
 * activity, for a cancellation — and when the journal wrote it.
 */
final readonly class Entry
{
    public function __construct(
        public int $position,
        public EntryKind $kind,
        public Activity $activity,
        public DateTimeImmutable $at,
    ) {
        if ($position < 1) {
            throw InvalidActivity::because(\sprintf('A position in the journal starts at 1; %d is not one.', $position));
        }
    }

    public function isCancellation(): bool
    {
        return EntryKind::Cancelled === $this->kind;
    }
}
