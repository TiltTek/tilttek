<?php

declare(strict_types=1);

namespace Tilttek\Leaderboards;

use DateTimeImmutable;
use Tilttek\Leaderboards\Period\Period;

/**
 * A board in a period as one viewer sees it: the first lines, the viewer's own line — also when it is not among them
 * —, how many members are ranked, and when the ranking was frozen, for a snapshot.
 */
final readonly class Standing
{
    /**
     * @param list<Row> $rows
     */
    public function __construct(
        public Board $board,
        public Period $period,
        public array $rows,
        public ?Row $own = null,
        public int $participants = 0,
        public ?DateTimeImmutable $frozenAt = null,
    ) {
    }

    /**
     * Whether the viewer's own line is among the first lines.
     */
    public function ownIsListed(): bool
    {
        return array_any($this->rows, static fn (Row $row): bool => $row->self);
    }
}
