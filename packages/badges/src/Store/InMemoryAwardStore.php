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
     * The awards held.
     *
     * @var array<string, Award>
     */
    private array $awards = [];

    /**
     * The awards held whose notice is due.
     *
     * @var array<string, Award>
     */
    private array $due = [];

    /**
     * The withdrawn awards whose notice is due.
     *
     * @var array<string, Award>
     */
    private array $withdrawn = [];

    #[Override]
    public function add(Award $award): bool
    {
        $key = $this->key($award);

        if (isset($this->awards[$key]) || isset($this->withdrawn[$key])) {
            return false;
        }

        $this->awards[$key] = $award;

        if (!$award->manual) {
            $this->due[$key] = $award;
        }

        return true;
    }

    #[Override]
    public function markDelivered(Award $award): void
    {
        unset($this->due[$this->key($award)]);
    }

    #[Override]
    public function undelivered(ResourceReference $member): array
    {
        return $this->ordered($this->due, $member);
    }

    #[Override]
    public function withdraw(Award $award): bool
    {
        $key = $this->key($award);

        if (!isset($this->awards[$key])) {
            return false;
        }

        // Its own notice may be under way: the notice of its withdrawal is due all the same.
        $this->withdrawn[$key] = $this->awards[$key];
        unset($this->awards[$key], $this->due[$key]);

        return true;
    }

    #[Override]
    public function withdrawn(ResourceReference $member): array
    {
        return $this->ordered($this->withdrawn, $member);
    }

    #[Override]
    public function remove(Award $award): void
    {
        $key = $this->key($award);
        unset($this->awards[$key], $this->due[$key], $this->withdrawn[$key]);
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
        $derived = static fn (Award $award): bool => !$award->manual && (!$since instanceof DateTimeImmutable || $award->awardedAt >= $since);
        $forgotten = array_filter($this->awards, $derived);
        $this->awards = array_diff_key($this->awards, $forgotten);
        $this->withdrawn = array_diff_key($this->withdrawn, array_filter($this->withdrawn, $derived));
        $given = array_diff_key($forgotten, $this->due);
        $this->due = array_diff_key($this->due, $forgotten);

        return array_values($given);
    }

    #[Override]
    public function eraseMember(ResourceReference $member): int
    {
        $count = \count($this->awards) + \count($this->withdrawn);
        $kept = static fn (Award $award): bool => !$award->member->equals($member);
        $this->awards = array_filter($this->awards, $kept);
        $this->withdrawn = array_filter($this->withdrawn, $kept);
        $this->due = array_filter($this->due, $kept);

        return $count - \count($this->awards) - \count($this->withdrawn);
    }

    /**
     * Empties the store: a long-running process, such as a worker that serves one request after another, calls it
     * between two of them, so that each starts as a new process would.
     */
    public function reset(): void
    {
        $this->awards = [];
        $this->due = [];
        $this->withdrawn = [];
    }

    /**
     * The member's awards among these, by badge and number.
     *
     * @param array<string, Award> $awards
     *
     * @return list<Award>
     */
    private function ordered(array $awards, ResourceReference $member): array
    {
        $awards = array_filter($awards, static fn (Award $award): bool => $award->member->equals($member));
        usort($awards, static fn (Award $a, Award $b): int => [$a->badge, $a->number] <=> [$b->badge, $b->number]);

        return $awards;
    }

    private function key(Award $award): string
    {
        return json_encode([$award->badge, $award->member->toString(), $award->number], \JSON_THROW_ON_ERROR);
    }
}
