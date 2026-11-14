<?php

declare(strict_types=1);

namespace Tilttek\AdventCalendar\Store;

use Override;
use Phil\Resource\ResourceReference;
use Tilttek\AdventCalendar\Contract\OpeningStore;
use Tilttek\AdventCalendar\Opening;

/**
 * The openings in memory, forgotten with the process: for tests and for an application that keeps them elsewhere.
 */
final class InMemoryOpeningStore implements OpeningStore
{
    /**
     * @var array<string, array<string, array<int, Opening>>> calendar, member, day
     */
    private array $openings = [];

    /**
     * @var array<string, array<string, array<int, Opening>>> calendar, member, day: the openings not yet delivered
     */
    private array $undelivered = [];

    #[Override]
    public function find(string $calendar, ResourceReference $member, int $day): ?Opening
    {
        return $this->openings[$calendar][$member->toString()][$day] ?? null;
    }

    #[Override]
    public function add(Opening $opening): bool
    {
        $member = $opening->member->toString();

        if (isset($this->openings[$opening->calendar][$member][$opening->day])) {
            return false;
        }

        $this->openings[$opening->calendar][$member][$opening->day] = $opening;
        $this->undelivered[$opening->calendar][$member][$opening->day] = $opening;

        return true;
    }

    #[Override]
    public function undelivered(ResourceReference $member): array
    {
        $key = $member->toString();
        $undelivered = [];
        ksort($this->undelivered);

        foreach ($this->undelivered as $members) {
            $openings = $members[$key] ?? [];
            ksort($openings);

            foreach ($openings as $opening) {
                $undelivered[] = $opening;
            }
        }

        return $undelivered;
    }

    #[Override]
    public function markDelivered(Opening $opening): void
    {
        unset($this->undelivered[$opening->calendar][$opening->member->toString()][$opening->day]);
    }

    #[Override]
    public function openingsOf(string $calendar, ResourceReference $member): array
    {
        $openings = $this->openings[$calendar][$member->toString()] ?? [];
        ksort($openings);

        return array_values($openings);
    }

    #[Override]
    public function eraseMember(ResourceReference $member): int
    {
        $key = $member->toString();
        $removed = 0;

        foreach (array_keys($this->openings) as $calendar) {
            $removed += \count($this->openings[$calendar][$key] ?? []);
            unset($this->openings[$calendar][$key], $this->undelivered[$calendar][$key]);
        }

        return $removed;
    }
}
