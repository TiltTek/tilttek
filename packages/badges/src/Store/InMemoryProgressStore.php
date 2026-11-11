<?php

declare(strict_types=1);

namespace Tilttek\Badges\Store;

use DateTimeImmutable;
use Override;
use Phil\Resource\ResourceReference;
use Tilttek\Badges\Contribution;

/**
 * The contributions in memory, forgotten with the process: for tests, scripts and examples.
 */
final class InMemoryProgressStore implements ProgressStore
{
    /**
     * @var array<string, Contribution>
     */
    private array $contributions = [];

    #[Override]
    public function add(Contribution $contribution): bool
    {
        $key = json_encode([$contribution->badge, $contribution->path, $contribution->activity], \JSON_THROW_ON_ERROR);

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

        return array_values($removed);
    }

    #[Override]
    public function count(string $badge, string $path, ResourceReference $member): int
    {
        return \count($this->of($badge, $path, $member));
    }

    #[Override]
    public function quantity(string $badge, string $path, ResourceReference $member): int
    {
        return array_sum(array_map(static fn (Contribution $contribution): int => $contribution->quantity, $this->of($badge, $path, $member)));
    }

    #[Override]
    public function moments(string $badge, string $path, ResourceReference $member): array
    {
        return array_map(static fn (Contribution $contribution): DateTimeImmutable => $contribution->occurredAt, $this->of($badge, $path, $member));
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
     * @return list<Contribution>
     */
    private function of(string $badge, string $path, ResourceReference $member): array
    {
        return array_values(array_filter(
            $this->contributions,
            static fn (Contribution $contribution): bool => $contribution->badge === $badge && $contribution->path === $path && $contribution->member->equals($member),
        ));
    }
}
