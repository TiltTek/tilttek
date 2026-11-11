<?php

declare(strict_types=1);

namespace Tilttek\Quests\Store;

use DateTimeImmutable;
use Override;
use Phil\Resource\ResourceReference;
use Tilttek\Quests\Contribution;

/**
 * The contributions in memory, forgotten with the process: for tests, scripts and examples.
 */
final class InMemoryContributionStore implements ContributionStore
{
    /**
     * @var array<string, Contribution>
     */
    private array $contributions = [];

    #[Override]
    public function add(Contribution $contribution): bool
    {
        $key = json_encode([$contribution->quest, $contribution->step, $contribution->activity], \JSON_THROW_ON_ERROR);

        if (isset($this->contributions[$key])) {
            return false;
        }

        $this->contributions[$key] = $contribution;

        return true;
    }

    #[Override]
    public function remove(string $activity): array
    {
        $removed = array_filter($this->contributions, static fn (Contribution $contribution): bool => $contribution->activity === $activity);
        $this->contributions = array_diff_key($this->contributions, $removed);

        return $this->sorted($removed);
    }

    #[Override]
    public function of(string $quest, ?array $members = null, ?DateTimeImmutable $from = null, ?DateTimeImmutable $until = null): array
    {
        $names = null === $members ? null : array_map(static fn (ResourceReference $member): string => $member->toString(), $members);

        return $this->sorted(array_filter(
            $this->contributions,
            static fn (Contribution $contribution): bool => $contribution->quest === $quest
                && (null === $names || \in_array($contribution->member->toString(), $names, true))
                && (!$from instanceof DateTimeImmutable || $contribution->occurredAt >= $from)
                && (!$until instanceof DateTimeImmutable || $contribution->occurredAt < $until),
        ));
    }

    #[Override]
    public function ofMember(ResourceReference $member): array
    {
        return $this->sorted(array_filter($this->contributions, static fn (Contribution $contribution): bool => $contribution->member->equals($member)));
    }

    #[Override]
    public function forget(?DateTimeImmutable $since): int
    {
        $count = \count($this->contributions);
        $this->contributions = array_filter($this->contributions, static fn (Contribution $contribution): bool => $since instanceof DateTimeImmutable && $contribution->occurredAt < $since);

        return $count - \count($this->contributions);
    }

    #[Override]
    public function eraseMember(ResourceReference $member): int
    {
        $count = \count($this->contributions);
        $this->contributions = array_filter($this->contributions, static fn (Contribution $contribution): bool => !$contribution->member->equals($member));

        return $count - \count($this->contributions);
    }

    /**
     * Empties the store: a long-running process, such as a worker that serves one request after another, calls it
     * between two of them, so that each starts as a new process would.
     */
    public function reset(): void
    {
        $this->contributions = [];
    }

    /**
     * @param array<string, Contribution> $contributions
     *
     * @return list<Contribution>
     */
    private function sorted(array $contributions): array
    {
        usort($contributions, static fn (Contribution $a, Contribution $b): int => [$a->occurredAt->getTimestamp(), $a->activity, $a->quest, $a->step] <=> [$b->occurredAt->getTimestamp(), $b->activity, $b->quest, $b->step]);

        return $contributions;
    }
}
