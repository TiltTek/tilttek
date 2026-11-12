<?php

declare(strict_types=1);

namespace Tilttek\AdventCalendar\Tests\Unit\Fixture;

use Override;
use Phil\Resource\ResourceReference;
use Tilttek\AdventCalendar\Contract\OpeningStore;
use Tilttek\AdventCalendar\Opening;
use Tilttek\AdventCalendar\Store\InMemoryOpeningStore;

/**
 * A store where another request opens the box between the read and the write: the first `find()` answers nothing, and
 * `add()` finds the other request's opening already kept.
 */
final class RacingOpeningStore implements OpeningStore
{
    private readonly InMemoryOpeningStore $inner;

    private bool $raced = false;

    public function __construct(private readonly Opening $other)
    {
        $this->inner = new InMemoryOpeningStore();
    }

    #[Override]
    public function find(string $calendar, ResourceReference $member, int $day): ?Opening
    {
        return $this->raced ? $this->inner->find($calendar, $member, $day) : null;
    }

    #[Override]
    public function add(Opening $opening): bool
    {
        $this->raced = true;
        $this->inner->add($this->other);

        return $this->inner->add($opening);
    }

    #[Override]
    public function openingsOf(string $calendar, ResourceReference $member): array
    {
        return $this->inner->openingsOf($calendar, $member);
    }

    #[Override]
    public function eraseMember(ResourceReference $member): int
    {
        return $this->inner->eraseMember($member);
    }
}
