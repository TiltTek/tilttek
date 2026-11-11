<?php

declare(strict_types=1);

namespace Tilttek\Badges\Store;

use DateTimeImmutable;
use Override;
use Phil\Resource\ResourceReference;
use Tilttek\Badges\Award;

/**
 * The awards in memory, forgotten with the process: for tests, scripts and examples.
 */
final class InMemoryAwardStore implements AwardStore
{
    /**
     * @var array<string, Award>
     */
    private array $awards = [];

    #[Override]
    public function add(Award $award): bool
    {
        $key = $this->key($award);

        if (isset($this->awards[$key])) {
            return false;
        }

        $this->awards[$key] = $award;

        return true;
    }

    #[Override]
    public function remove(Award $award): void
    {
        unset($this->awards[$this->key($award)]);
    }

    #[Override]
    public function of(ResourceReference $member, ?string $badge = null): array
    {
        $awards = array_filter(
            $this->awards,
            static fn (Award $award): bool => $award->member->equals($member) && (null === $badge || $award->badge === $badge),
        );
        usort($awards, static fn (Award $a, Award $b): int => [$a->awardedAt->getTimestamp(), $a->badge, $a->number] <=> [$b->awardedAt->getTimestamp(), $b->badge, $b->number]);

        return $awards;
    }

    #[Override]
    public function forgetDerived(?DateTimeImmutable $since): array
    {
        $forgotten = array_filter($this->awards, static fn (Award $award): bool => !$award->manual && (!$since instanceof DateTimeImmutable || $award->awardedAt >= $since));
        $this->awards = array_diff_key($this->awards, $forgotten);

        return array_values($forgotten);
    }

    #[Override]
    public function eraseMember(ResourceReference $member): int
    {
        $count = \count($this->awards);
        $this->awards = array_filter($this->awards, static fn (Award $award): bool => !$award->member->equals($member));

        return $count - \count($this->awards);
    }

    /**
     * Empties the store: a long-running process, such as a worker that serves one request after another, calls it
     * between two of them, so that each starts as a new process would.
     */
    public function reset(): void
    {
        $this->awards = [];
    }

    private function key(Award $award): string
    {
        return json_encode([$award->badge, $award->member->toString(), $award->number], \JSON_THROW_ON_ERROR);
    }
}
