<?php

declare(strict_types=1);

namespace Tilttek\FriendChallenges\Store;

use DateTimeImmutable;
use Override;
use Phil\Resource\ResourceReference;
use Tilttek\FriendChallenges\Challenge;
use Tilttek\FriendChallenges\Contract\ChallengeStore;
use Tilttek\FriendChallenges\Exception\ChallengeConflict;

/**
 * The challenges in memory, for the tests and the demonstrations: kept as records, as a database would keep them.
 */
final class InMemoryChallengeStore implements ChallengeStore
{
    /** @var array<array-key, array<array-key, mixed>> the records by identifier; PHP keeps a numeric one as an integer */
    private array $records = [];

    #[Override]
    public function get(string $id): ?Challenge
    {
        return isset($this->records[$id]) ? Challenge::fromArray($this->records[$id]) : null;
    }

    #[Override]
    public function add(Challenge $challenge): void
    {
        if (isset($this->records[$challenge->id])) {
            throw ChallengeConflict::exists($challenge->id);
        }

        $this->records[$challenge->id] = $challenge->toArray();
    }

    #[Override]
    public function update(Challenge $challenge, int $expected): void
    {
        if (($this->records[$challenge->id]['version'] ?? null) !== $expected) {
            throw ChallengeConflict::stale($challenge->id, $expected);
        }

        $this->records[$challenge->id] = $challenge->toArray();
    }

    #[Override]
    public function due(DateTimeImmutable $at, int $limit): array
    {
        $due = [];

        foreach ($this->records as $id => $record) {
            $challenge = Challenge::fromArray($record);

            if ($challenge->isDue($at)) {
                $due[$id] = $challenge->reviewEndsAt->format('U.u');
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

            if (Challenge::fromArray($record)->involves($member)) {
                $involving[] = (string) $id;
            }
        }

        return $involving;
    }
}
