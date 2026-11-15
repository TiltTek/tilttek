<?php

declare(strict_types=1);

namespace Tilttek\LeaderboardsBundle\Infrastructure\Lead;

use Override;
use Phil\Resource\ResourceReference;
use Tilttek\LeaderboardsBundle\Infrastructure\Lead\Contract\LeadStore;

/**
 * The leads taken in memory, forgotten with the process: for tests, scripts and the store `memory`.
 */
final class InMemoryLeadStore implements LeadStore
{
    /**
     * @var array<string, array<string, Lead>> by member, then by board and period
     */
    private array $leads = [];

    #[Override]
    public function claim(Lead $lead): bool
    {
        $key = $this->key($lead->board, $lead->period);

        if (isset($this->leads[$lead->member->toString()][$key])) {
            return false;
        }

        $this->leads[$lead->member->toString()][$key] = $lead;

        return true;
    }

    #[Override]
    public function has(string $board, string $period, ResourceReference $member): bool
    {
        return isset($this->leads[$member->toString()][$this->key($board, $period)]);
    }

    #[Override]
    public function leads(ResourceReference $member): array
    {
        $leads = $this->leads[$member->toString()] ?? [];
        ksort($leads, \SORT_STRING);

        return array_values($leads);
    }

    #[Override]
    public function eraseMember(ResourceReference $member): int
    {
        $count = \count($this->leads[$member->toString()] ?? []);
        unset($this->leads[$member->toString()]);

        return $count;
    }

    /**
     * Empties the store: a long-running process, such as a worker that serves one request after another, calls it
     * between two of them, so that each starts as a new process would.
     */
    public function reset(): void
    {
        $this->leads = [];
    }

    private function key(string $board, string $period): string
    {
        return $board."\0".$period;
    }
}
