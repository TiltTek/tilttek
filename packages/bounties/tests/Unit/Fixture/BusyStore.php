<?php

declare(strict_types=1);

namespace Tilttek\Bounties\Tests\Unit\Fixture;

use DateTimeImmutable;
use Override;
use Phil\Resource\ResourceReference;
use Tilttek\Bounties\Bounty;
use Tilttek\Bounties\Contract\BountyStore;
use Tilttek\Bounties\Exception\BountyConflict;
use Tilttek\Bounties\Store\InMemoryBountyStore;

/**
 * A store another writer reaches first: its next updates fail, as many as asked, and an addition may find another
 * bounty added just before.
 */
final class BusyStore implements BountyStore
{
    public int $updates = 0;

    public int $failures = 0;

    /** @var list<string> identifiers the store gives as due before the real ones, whatever they name */
    public array $extraDue = [];

    public ?int $limit = null;

    public ?Bounty $addedFirst = null;

    public function __construct(
        private readonly InMemoryBountyStore $store = new InMemoryBountyStore(),
    ) {
    }

    #[Override]
    public function get(string $id): ?Bounty
    {
        return $this->store->get($id);
    }

    #[Override]
    public function openOn(ResourceReference $subject): ?string
    {
        return $this->store->openOn($subject);
    }

    #[Override]
    public function openBy(ResourceReference $sponsor): int
    {
        return $this->store->openBy($sponsor);
    }

    #[Override]
    public function add(Bounty $bounty): void
    {
        if ($this->addedFirst instanceof Bounty) {
            $this->store->add($this->addedFirst);
            $this->addedFirst = null;
        }

        $this->store->add($bounty);
    }

    #[Override]
    public function update(Bounty $bounty, int $expected): void
    {
        ++$this->updates;

        if ($this->failures > 0) {
            --$this->failures;

            throw BountyConflict::stale($bounty->id, $expected);
        }

        $this->store->update($bounty, $expected);
    }

    #[Override]
    public function due(DateTimeImmutable $at, int $limit): array
    {
        $this->limit = $limit;

        return [...$this->extraDue, ...$this->store->due($at, $limit)];
    }

    #[Override]
    public function involving(ResourceReference $member, int $limit): array
    {
        $this->limit = $limit;

        return $this->store->involving($member, $limit);
    }
}
