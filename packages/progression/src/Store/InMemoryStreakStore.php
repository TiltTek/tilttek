<?php

declare(strict_types=1);

namespace Tilttek\Progression\Store;

use DateTimeImmutable;
use Override;
use Phil\Resource\ResourceReference;
use Tilttek\Progression\Streak\StreakGesture;
use Tilttek\Progression\Streak\StreakState;

/**
 * The streaks in memory, forgotten with the process: for tests, scripts and examples.
 */
final class InMemoryStreakStore implements StreakStore
{
    /**
     * @var array<string, StreakGesture>
     */
    private array $gestures = [];

    /**
     * @var array<string, array{string, ResourceReference, int}>
     */
    private array $frozen = [];

    /**
     * @var array<string, StreakState>
     */
    private array $states = [];

    #[Override]
    public function add(StreakGesture $gesture): bool
    {
        $key = json_encode([$gesture->streak, $gesture->activity], \JSON_THROW_ON_ERROR);

        if (isset($this->gestures[$key])) {
            return false;
        }

        $this->gestures[$key] = $gesture;

        return true;
    }

    #[Override]
    public function remove(string $activity): array
    {
        $removed = array_filter($this->gestures, static fn (StreakGesture $gesture): bool => $gesture->activity === $activity);
        $this->gestures = array_diff_key($this->gestures, $removed);

        return array_values($removed);
    }

    #[Override]
    public function units(string $streak, ResourceReference $member): array
    {
        $units = [];

        foreach ($this->gestures as $gesture) {
            if ($gesture->streak === $streak && $gesture->member->equals($member)) {
                $units[$gesture->unit] = $gesture->unit;
            }
        }

        sort($units);

        return $units;
    }

    #[Override]
    public function freeze(string $streak, ResourceReference $member, int $unit): bool
    {
        $key = json_encode([$streak, $member->toString(), $unit], \JSON_THROW_ON_ERROR);

        if (isset($this->frozen[$key])) {
            return false;
        }

        $this->frozen[$key] = [$streak, $member, $unit];

        return true;
    }

    #[Override]
    public function thaw(string $streak, ResourceReference $member, int $unit): bool
    {
        $key = json_encode([$streak, $member->toString(), $unit], \JSON_THROW_ON_ERROR);
        $frozen = isset($this->frozen[$key]);
        unset($this->frozen[$key]);

        return $frozen;
    }

    #[Override]
    public function frozen(string $streak, ResourceReference $member): array
    {
        $units = [];

        foreach ($this->frozen as [$name, $holder, $unit]) {
            if ($name === $streak && $holder->equals($member)) {
                $units[] = $unit;
            }
        }

        sort($units);

        return $units;
    }

    #[Override]
    public function state(string $streak, ResourceReference $member): ?StreakState
    {
        return $this->states[$this->key($streak, $member)] ?? null;
    }

    #[Override]
    public function save(StreakState $state): void
    {
        $this->states[$this->key($state->streak, $state->member)] = $state;
    }

    #[Override]
    public function discard(string $streak, ResourceReference $member): void
    {
        unset($this->states[$this->key($streak, $member)]);
    }

    #[Override]
    public function alive(string $streak, int $before): array
    {
        return array_values(array_filter($this->states, static fn (StreakState $state): bool => $state->streak === $streak && $state->isAlive() && $state->lastActive < $before));
    }

    #[Override]
    public function forget(?DateTimeImmutable $since): int
    {
        $count = \count($this->gestures);
        $this->gestures = array_filter($this->gestures, static fn (StreakGesture $gesture): bool => $since instanceof DateTimeImmutable && $gesture->occurredAt < $since);

        return $count - \count($this->gestures);
    }

    #[Override]
    public function eraseMember(ResourceReference $member): int
    {
        $count = \count($this->gestures) + \count($this->frozen) + \count($this->states);
        $this->gestures = array_filter($this->gestures, static fn (StreakGesture $gesture): bool => !$gesture->member->equals($member));
        $this->frozen = array_filter($this->frozen, static fn (array $frozen): bool => !$frozen[1]->equals($member));
        $this->states = array_filter($this->states, static fn (StreakState $state): bool => !$state->member->equals($member));

        return $count - \count($this->gestures) - \count($this->frozen) - \count($this->states);
    }

    /**
     * Empties the store: a long-running process, such as a worker that serves one request after another, calls it
     * between two of them, so that each starts as a new process would.
     */
    public function reset(): void
    {
        $this->gestures = [];
        $this->frozen = [];
        $this->states = [];
    }

    private function key(string $streak, ResourceReference $member): string
    {
        return json_encode([$streak, $member->toString()], \JSON_THROW_ON_ERROR);
    }
}
