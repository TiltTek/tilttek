<?php

declare(strict_types=1);

namespace Tilttek\AdventCalendar\Contract;

use Phil\Resource\ResourceReference;
use Tilttek\AdventCalendar\Opening;

/**
 * Where the openings are kept. One opening at most per calendar, member and day: `add()` is the guard that makes an
 * opening happen once, even when two requests race. Each opening is kept with whether its `BoxOpened` was delivered.
 */
interface OpeningStore
{
    public function find(string $calendar, ResourceReference $member, int $day): ?Opening;

    /**
     * Keeps the opening unless the member already opened that box of that calendar.
     *
     * @return bool true when it was kept, false when one was there already
     */
    public function add(Opening $opening): bool;

    /**
     * The member's openings not yet marked delivered, in every calendar, by calendar then day. An opening is added
     * undelivered.
     *
     * @return list<Opening>
     */
    public function undelivered(ResourceReference $member): array;

    /**
     * Marks the opening of that calendar, member and day delivered, its `BoxOpened` dispatched without an exception;
     * nothing when the store holds no such opening, or holds it delivered.
     */
    public function markDelivered(Opening $opening): void;

    /**
     * @return list<Opening> the member's openings of the calendar, by day
     */
    public function openingsOf(string $calendar, ResourceReference $member): array;

    /**
     * Removes every opening of the member, in every calendar.
     *
     * @return int how many were removed
     */
    public function eraseMember(ResourceReference $member): int;
}
