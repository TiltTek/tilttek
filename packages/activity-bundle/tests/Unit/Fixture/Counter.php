<?php

declare(strict_types=1);

namespace Tilttek\ActivityBundle\Tests\Unit\Fixture;

use DateTimeImmutable;
use Override;
use RuntimeException;
use Tilttek\Activity\Entry;
use Tilttek\Activity\Filter;
use Tilttek\Activity\Projection\Projection;

/**
 * A projection that counts quantities by member, keeps the positions it applied, and remembers its resets; it fails
 * on a position it is told to fail on.
 */
final class Counter implements Projection
{
    /**
     * @var array<string, int>
     */
    public array $totals = [];

    /**
     * @var list<int>
     */
    public array $applied = [];

    /**
     * @var list<?string>
     */
    public array $resets = [];

    public ?int $failOn = null;

    public function __construct(
        private readonly string $name = 'counter',
        private readonly ?Filter $filter = null,
    ) {
    }

    #[Override]
    public function name(): string
    {
        return $this->name;
    }

    #[Override]
    public function filter(): Filter
    {
        return $this->filter ?? Filter::all();
    }

    #[Override]
    public function apply(Entry $entry): void
    {
        if ($entry->position === $this->failOn) {
            throw new RuntimeException('Failed on '.$entry->position.'.');
        }

        $member = $entry->activity->member->toString();
        $this->totals[$member] = ($this->totals[$member] ?? 0) + ($entry->isCancellation() ? -1 : 1) * $entry->activity->quantity;
        $this->applied[] = $entry->position;
    }

    #[Override]
    public function reset(?DateTimeImmutable $since): void
    {
        $this->resets[] = $since?->format('Y-m-d');
        $this->totals = [];
    }
}
