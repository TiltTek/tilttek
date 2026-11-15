<?php

declare(strict_types=1);

namespace Tilttek\FriendChallenges\Tests\Unit\Fixture;

use DateTimeImmutable;
use Override;
use Phil\Resource\ResourceReference;
use Tilttek\FriendChallenges\Challenge;
use Tilttek\FriendChallenges\Contract\ChallengeStore;
use Tilttek\FriendChallenges\Exception\ChallengeConflict;
use Tilttek\FriendChallenges\Store\InMemoryChallengeStore;

/**
 * A store another writer reaches first: its next updates fail, as many as asked, and an addition may fail once after
 * another writer added the same challenge.
 */
final class BusyStore implements ChallengeStore
{
    public int $updates = 0;

    public int $failures = 0;

    /** @var list<string> identifiers the store gives as due before the real ones, whatever they name */
    public array $extraDue = [];

    public ?int $limit = null;

    public ?Challenge $addedFirst = null;

    public function __construct(
        private readonly InMemoryChallengeStore $store = new InMemoryChallengeStore(),
    ) {
    }

    #[Override]
    public function get(string $id): ?Challenge
    {
        return $this->store->get($id);
    }

    #[Override]
    public function add(Challenge $challenge): void
    {
        if ($this->addedFirst instanceof Challenge) {
            $this->store->add($this->addedFirst);
            $this->addedFirst = null;
        }

        $this->store->add($challenge);
    }

    #[Override]
    public function update(Challenge $challenge, int $expected): void
    {
        ++$this->updates;

        if ($this->failures > 0) {
            --$this->failures;

            throw ChallengeConflict::stale($challenge->id, $expected);
        }

        $this->store->update($challenge, $expected);
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
