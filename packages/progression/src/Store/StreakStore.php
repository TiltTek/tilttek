<?php

declare(strict_types=1);

namespace Tilttek\Progression\Store;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use Tilttek\Progression\Streak\StreakGesture;
use Tilttek\Progression\Streak\StreakState;

/**
 * Where the streaks are kept: the gestures that made a unit active, one per streak and activity; the units a member
 * froze; and the state of each member's streak, to tell when it breaks.
 */
interface StreakStore
{
    /**
     * Keeps the gesture; false when the activity already made a unit of this streak active.
     */
    public function add(StreakGesture $gesture): bool;

    /**
     * Removes every gesture of the activity, for its cancellation, and returns them.
     *
     * @return list<StreakGesture>
     */
    public function remove(string $activity): array;

    /**
     * The member's active units, sorted, each once.
     *
     * @return list<int>
     */
    public function units(string $streak, ResourceReference $member): array;

    /**
     * Freezes a unit: false when it was frozen already.
     */
    public function freeze(string $streak, ResourceReference $member, int $unit): bool;

    /**
     * Thaws a unit: false when it was not frozen.
     */
    public function thaw(string $streak, ResourceReference $member, int $unit): bool;

    /**
     * The member's frozen units, sorted.
     *
     * @return list<int>
     */
    public function frozen(string $streak, ResourceReference $member): array;

    public function state(string $streak, ResourceReference $member): ?StreakState;

    public function save(StreakState $state): void;

    public function discard(string $streak, ResourceReference $member): void;

    /**
     * The states of the streak still alive whose last active unit is before `before`: those that may have broken.
     *
     * @return list<StreakState>
     */
    public function alive(string $streak, int $before): array;

    /**
     * Forgets the gestures of the activities from `since` on — all of them when `since` is null —, before a rebuild.
     *
     * @return int how many were forgotten
     */
    public function forget(?DateTimeImmutable $since): int;

    /**
     * @return int how many gestures, frozen units and states of the member were removed
     */
    public function eraseMember(ResourceReference $member): int;
}
