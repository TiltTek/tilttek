<?php

declare(strict_types=1);

namespace Tilttek\Progression\Store;

use DateTimeImmutable;
use Override;
use Phil\Resource\ResourceReference;
use Tilttek\Progression\Experience\Gain;

/**
 * The experience in memory, forgotten with the process: for tests, scripts and examples.
 */
final class InMemoryExperienceStore implements ExperienceStore
{
    /**
     * @var array<string, Gain>
     */
    private array $gains = [];

    #[Override]
    public function add(Gain $gain): bool
    {
        $key = json_encode([$gain->track, $gain->rule, $gain->activity], \JSON_THROW_ON_ERROR);

        if (isset($this->gains[$key])) {
            return false;
        }

        $this->gains[$key] = $gain;

        return true;
    }

    #[Override]
    public function remove(string $activity): array
    {
        $removed = array_filter($this->gains, static fn (Gain $gain): bool => $gain->activity === $activity);
        $this->gains = array_diff_key($this->gains, $removed);

        return array_values($removed);
    }

    #[Override]
    public function total(string $track, ResourceReference $subject, string $season): int
    {
        return $this->sum(array_filter($this->on($track, $subject), static fn (Gain $gain): bool => $gain->season === $season));
    }

    #[Override]
    public function lifetime(string $track, ResourceReference $subject): int
    {
        return $this->sum($this->on($track, $subject));
    }

    #[Override]
    public function seasons(string $track, ResourceReference $subject): array
    {
        $seasons = [];

        foreach ($this->on($track, $subject) as $gain) {
            if ('' !== $gain->season) {
                $seasons[$gain->season] = new Total($track, $subject, $gain->season, $this->total($track, $subject, $gain->season));
            }
        }

        return array_values($seasons);
    }

    #[Override]
    public function earned(string $track, string $rule, ResourceReference $subject, ?DateTimeImmutable $since, ?DateTimeImmutable $until): int
    {
        return $this->sum(array_filter(
            $this->on($track, $subject),
            static fn (Gain $gain): bool => $gain->rule === $rule
                && (!$since instanceof DateTimeImmutable || $gain->occurredAt >= $since)
                && (!$until instanceof DateTimeImmutable || $gain->occurredAt < $until),
        ));
    }

    #[Override]
    public function totalsSince(?DateTimeImmutable $since): array
    {
        $totals = [];

        foreach ($this->gains as $gain) {
            if (!$since instanceof DateTimeImmutable || $gain->occurredAt >= $since) {
                $totals[json_encode([$gain->track, $gain->subject->toString(), $gain->season], \JSON_THROW_ON_ERROR)] = new Total($gain->track, $gain->subject, $gain->season, $this->total($gain->track, $gain->subject, $gain->season));
            }
        }

        return array_values($totals);
    }

    #[Override]
    public function of(ResourceReference $subject): array
    {
        $gains = array_filter($this->gains, static fn (Gain $gain): bool => $gain->subject->equals($subject));
        usort($gains, static fn (Gain $a, Gain $b): int => $a->occurredAt <=> $b->occurredAt);

        return $gains;
    }

    #[Override]
    public function forget(?DateTimeImmutable $since): int
    {
        $count = \count($this->gains);
        $this->gains = array_filter($this->gains, static fn (Gain $gain): bool => $since instanceof DateTimeImmutable && $gain->occurredAt < $since);

        return $count - \count($this->gains);
    }

    #[Override]
    public function eraseMember(ResourceReference $member): int
    {
        $count = \count($this->gains);
        $this->gains = array_filter($this->gains, static fn (Gain $gain): bool => !$gain->subject->equals($member) && !$gain->member->equals($member));

        return $count - \count($this->gains);
    }

    /**
     * Empties the store: a long-running process, such as a worker that serves one request after another, calls it
     * between two of them, so that each starts as a new process would.
     */
    public function reset(): void
    {
        $this->gains = [];
    }

    /**
     * @return array<string, Gain>
     */
    private function on(string $track, ResourceReference $subject): array
    {
        return array_filter($this->gains, static fn (Gain $gain): bool => $gain->track === $track && $gain->subject->equals($subject));
    }

    /**
     * @param array<string, Gain> $gains
     */
    private function sum(array $gains): int
    {
        return array_sum(array_map(static fn (Gain $gain): int => $gain->amount, $gains));
    }
}
