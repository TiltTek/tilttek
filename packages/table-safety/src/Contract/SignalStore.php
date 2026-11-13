<?php

declare(strict_types=1);

namespace Tilttek\TableSafety\Contract;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use Tilttek\TableSafety\Signal\Signal;

/**
 * Where the signals raised at each table are kept.
 */
interface SignalStore
{
    public function add(Signal $signal): void;

    /**
     * Marks the table's signal as seen by the game master, and returns it; null when the table has no such signal or it
     * was already seen.
     */
    public function acknowledge(ResourceReference $table, string $id, DateTimeImmutable $at): ?Signal;

    /**
     * The table's signals raised after a moment, or the last ones without it, in the order they were raised.
     *
     * @param positive-int $limit
     *
     * @return list<Signal>
     */
    public function recent(ResourceReference $table, ?DateTimeImmutable $after = null, int $limit = 100): array;

    /**
     * @return list<Signal> the named signals the participant raised, at every table
     */
    public function ofParticipant(ResourceReference $participant): array;

    /**
     * Takes the participant's name off the named signals they raised, which stay at their table as anonymous ones; the
     * number changed.
     */
    public function forgetParticipant(ResourceReference $participant): int;

    /**
     * Deletes the signals of every table raised before a moment; the number deleted.
     */
    public function purge(DateTimeImmutable $before): int;
}
