<?php

declare(strict_types=1);

namespace Tilttek\AdventCalendarBundle\Infrastructure\Memory;

use DateTimeZone;
use Override;
use Phil\Resource\ResourceReference;
use Symfony\Contracts\Service\ResetInterface;
use Tilttek\AdventCalendar\Contract\OpeningStore;
use Tilttek\AdventCalendar\Opening;
use Tilttek\AdventCalendar\Store\InMemoryOpeningStore;
use Tilttek\AdventCalendarBundle\Application\Contract\MemberOpenings;

/**
 * The openings in memory, for tests and demonstrations: the library's store, emptied after each request so that a
 * worker never keeps one member's openings for the next request. Moments are kept in UTC, as the DBAL store keeps them.
 */
final class InMemoryOpenings implements OpeningStore, MemberOpenings, ResetInterface
{
    private InMemoryOpeningStore $store;

    /** @var array<string, list<string>> the calendars each member opened a box of, in the order of their first opening */
    private array $calendars = [];

    public function __construct()
    {
        $this->store = new InMemoryOpeningStore();
    }

    #[Override]
    public function find(string $calendar, ResourceReference $member, int $day): ?Opening
    {
        return $this->store->find($calendar, $member, $day);
    }

    #[Override]
    public function add(Opening $opening): bool
    {
        $opening = new Opening($opening->calendar, $opening->member, $opening->day, $opening->openedAt->setTimezone(new DateTimeZone('UTC')));

        if (!$this->store->add($opening)) {
            return false;
        }

        $member = $opening->member->toString();
        $this->calendars[$member] = array_values(array_unique([...$this->calendars[$member] ?? [], $opening->calendar]));

        return true;
    }

    #[Override]
    public function undelivered(ResourceReference $member): array
    {
        return $this->store->undelivered($member);
    }

    #[Override]
    public function markDelivered(Opening $opening): void
    {
        $this->store->markDelivered($opening);
    }

    #[Override]
    public function openingsOf(string $calendar, ResourceReference $member): array
    {
        return $this->store->openingsOf($calendar, $member);
    }

    #[Override]
    public function eraseMember(ResourceReference $member): int
    {
        unset($this->calendars[$member->toString()]);

        return $this->store->eraseMember($member);
    }

    #[Override]
    public function ofMember(ResourceReference $member): array
    {
        $calendars = $this->calendars[$member->toString()] ?? [];
        sort($calendars);
        $openings = [];

        foreach ($calendars as $calendar) {
            $openings = [...$openings, ...$this->store->openingsOf($calendar, $member)];
        }

        return $openings;
    }

    #[Override]
    public function reset(): void
    {
        $this->store = new InMemoryOpeningStore();
        $this->calendars = [];
    }
}
