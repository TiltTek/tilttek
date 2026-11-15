<?php

declare(strict_types=1);

namespace Tilttek\Bounties\Store;

use DateTimeImmutable;
use Override;
use Phil\Resource\ResourceReference;
use Tilttek\Bounties\Bounty;
use Tilttek\Bounties\BountyState;
use Tilttek\Bounties\Contract\BountyStore;
use Tilttek\Bounties\Exception\BountyConflict;

/**
 * The bounties in memory, for the tests and the demonstrations: kept as records, as a database would keep them.
 */
final class InMemoryBountyStore implements BountyStore
{
    /** @var array<array-key, array<array-key, mixed>> the records by identifier; PHP keeps a numeric one as an integer */
    private array $records = [];

    #[Override]
    public function get(string $id): ?Bounty
    {
        return isset($this->records[$id]) ? Bounty::fromArray($this->records[$id]) : null;
    }

    #[Override]
    public function openOn(ResourceReference $subject): ?string
    {
        foreach ($this->records as $id => $record) {
            if (BountyState::Open->value === $record['state'] && $subject->toString() === $record['subject']) {
                return (string) $id;
            }
        }

        return null;
    }

    #[Override]
    public function openBy(ResourceReference $sponsor): int
    {
        return \count(array_filter($this->records, static fn (array $record): bool => BountyState::Open->value === $record['state'] && $sponsor->toString() === $record['sponsor']));
    }

    #[Override]
    public function add(Bounty $bounty): void
    {
        if (isset($this->records[$bounty->id])) {
            throw BountyConflict::exists($bounty->id);
        }

        if (null !== $this->openOn($bounty->subject)) {
            throw BountyConflict::subjectTaken($bounty->subject->toString());
        }

        $this->records[$bounty->id] = $bounty->toArray();
    }

    #[Override]
    public function update(Bounty $bounty, int $expected): void
    {
        if (($this->records[$bounty->id]['version'] ?? null) !== $expected) {
            throw BountyConflict::stale($bounty->id, $expected);
        }

        $this->records[$bounty->id] = $bounty->toArray();
    }

    #[Override]
    public function due(DateTimeImmutable $at, int $limit): array
    {
        $due = [];

        foreach ($this->records as $id => $record) {
            $bounty = Bounty::fromArray($record);

            if ($bounty->isDue($at)) {
                $due[$id] = $bounty->graceEndsAt->format('U.u');
            }
        }

        asort($due, \SORT_NUMERIC);

        return \array_slice(array_map(strval(...), array_keys($due)), 0, max(0, $limit));
    }

    #[Override]
    public function involving(ResourceReference $member, int $limit): array
    {
        $involving = [];

        foreach ($this->records as $id => $record) {
            if (\count($involving) >= $limit) {
                break;
            }

            if (Bounty::fromArray($record)->involves($member)) {
                $involving[] = (string) $id;
            }
        }

        return $involving;
    }
}
