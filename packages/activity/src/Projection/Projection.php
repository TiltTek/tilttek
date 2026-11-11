<?php

declare(strict_types=1);

namespace Tilttek\Activity\Projection;

use DateTimeImmutable;
use Tilttek\Activity\Entry;
use Tilttek\Activity\Filter;

/**
 * A reader of the journal that keeps its own state up to date — the points of a member, the progress of a badge, the
 * count of games played — so that the journal is read in full only when a rule changes.
 *
 * The `Projector` hands it the entries it has not seen, in order, and keeps where it stopped. An entry is applied
 * once in normal operation, but a crash between applying and saving the checkpoint hands it again: `apply()` must
 * tolerate seeing an entry twice, for instance by keeping the positions it applied.
 */
interface Projection
{
    /**
     * The name its checkpoint is kept under: 1 to 64 lower-case letters, digits, "_", "." or "-", starting with a
     * letter, such as `points` or `badges.progress`.
     */
    public function name(): string;

    /**
     * The activities it reads; the others are not handed to it.
     */
    public function filter(): Filter;

    /**
     * Applies an entry: a recorded activity, or the cancellation of one, to undo.
     */
    public function apply(Entry $entry): void;

    /**
     * Forgets what it derived from the activities from `since` on — all of it when `since` is null —, before those
     * activities are handed to it again.
     */
    public function reset(?DateTimeImmutable $since): void;
}
