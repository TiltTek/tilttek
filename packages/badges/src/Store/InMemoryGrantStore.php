<?php

declare(strict_types=1);

namespace Tilttek\Badges\Store;

use DateTimeImmutable;
use Override;
use Phil\Resource\ResourceReference;

/**
 * The consents in memory, forgotten with the process: for tests, scripts and examples.
 */
final class InMemoryGrantStore implements GrantStore
{
    /**
     * @var array<string, array<string, DateTimeImmutable>>
     */
    private array $grants = [];

    #[Override]
    public function grant(ResourceReference $member, string $client, DateTimeImmutable $at): void
    {
        $this->grants[$member->toString()][$client] = $at;
    }

    #[Override]
    public function withdraw(ResourceReference $member, string $client): bool
    {
        $known = $this->allows($member, $client);
        unset($this->grants[$member->toString()][$client]);

        return $known;
    }

    #[Override]
    public function allows(ResourceReference $member, string $client): bool
    {
        return isset($this->grants[$member->toString()][$client]);
    }

    #[Override]
    public function clients(ResourceReference $member): array
    {
        $clients = array_keys($this->grants[$member->toString()] ?? []);
        sort($clients);

        return $clients;
    }

    #[Override]
    public function eraseMember(ResourceReference $member): int
    {
        $count = \count($this->grants[$member->toString()] ?? []);
        unset($this->grants[$member->toString()]);

        return $count;
    }

    /**
     * Empties the store: a long-running process, such as a worker that serves one request after another, calls it
     * between two of them, so that each starts as a new process would.
     */
    public function reset(): void
    {
        $this->grants = [];
    }
}
