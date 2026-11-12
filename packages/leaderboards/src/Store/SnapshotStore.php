<?php

declare(strict_types=1);

namespace Tilttek\Leaderboards\Store;

use Phil\Resource\ResourceReference;
use Tilttek\Leaderboards\Placing;
use Tilttek\Leaderboards\Snapshot;

/**
 * Where the snapshots are kept: one per board and period, never rewritten.
 */
interface SnapshotStore
{
    /**
     * Keeps the snapshot; false when the board already has one for that period.
     */
    public function save(Snapshot $snapshot): bool;

    public function find(string $board, string $period): ?Snapshot;

    /**
     * The member's lines in every snapshot, for an export.
     *
     * @return list<Placing>
     */
    public function placings(ResourceReference $member): array;

    /**
     * Removes the member's lines from every snapshot; the others keep their ranks.
     *
     * @return int how many lines were removed
     */
    public function eraseMember(ResourceReference $member): int;
}
