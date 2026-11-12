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

        return true;
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
            unset($this->openings[$calendar][$key]);
        }

        return $removed;
    }
}
