<?php

declare(strict_types=1);

namespace Tilttek\Collections\Daily;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use Psr\Clock\ClockInterface;
use Tilttek\Collections\CollectionKey;
use Tilttek\Collections\Collections;
use Tilttek\Collections\Copy;
use Tilttek\Collections\Source;

/**
 * The item of the day, which a member gets by coming by that day: once per member and day, whatever the number of
 * visits, under a key the library derives from the day and the member.
 */
final readonly class Daily
{
    public function __construct(
        public DailyCalendar $calendar,
        private Collections $collections,
        private ClockInterface $clock,
    ) {
    }

    /**
     * The item of the day a moment falls on — now when none is given.
     */
    public function today(?DateTimeImmutable $at = null): DailyPick
    {
        return $this->calendar->pick($this->calendar->dayOf($at ?? $this->clock->now()));
    }

    /**
     * Gives the member the item of the day the moment falls on, once per member and day.
     */
    public function claim(ResourceReference $member, ?DateTimeImmutable $at = null): Copy
    {
        $at ??= $this->clock->now();
        $day = $this->calendar->dayOf($at);

        return $this->collections->grant($member, $this->calendar->pick($day)->item, Source::Daily, $this->key($day, $member), $at);
    }

    /**
     * Takes back the item of the day a moment falls on, if the member still has it and never traded it; returns
     * whether it did.
     */
    public function takeBack(ResourceReference $member, DateTimeImmutable $at): bool
    {
        return $this->collections->takeBack($member, $this->key($this->calendar->dayOf($at), $member));
    }

    /**
     * Forgets the items of the day given from a moment on — all of them when null — that never changed hands.
     */
    public function forget(?DateTimeImmutable $since): int
    {
        return $this->collections->copies->forget(Source::Daily, $since);
    }

    private function key(string $day, ResourceReference $member): CollectionKey
    {
        return CollectionKey::internal(\sprintf('daily/%s/%s', $day, hash('sha256', $member->toString())));
    }
}
