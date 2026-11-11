<?php

declare(strict_types=1);

namespace Tilttek\Progression\Store;

use Override;
use Phil\Resource\ResourceReference;
use Tilttek\Progression\ReachedMilestone;

/**
 * The milestones reached in memory, forgotten with the process: for tests, scripts and examples.
 */
final class InMemoryMilestoneStore implements MilestoneStore
{
    /**
     * @var array<string, ReachedMilestone>
     */
    private array $reached = [];

    #[Override]
    public function reach(ReachedMilestone $reached): bool
    {
        $key = json_encode([$reached->track, $reached->milestone, $reached->subject->toString(), $reached->season], \JSON_THROW_ON_ERROR);

        if (isset($this->reached[$key])) {
            return false;
        }

        $this->reached[$key] = $reached;

        return true;
    }

    #[Override]
    public function of(ResourceReference $subject): array
    {
        $reached = array_filter($this->reached, static fn (ReachedMilestone $reached): bool => $reached->subject->equals($subject));
        usort($reached, static fn (ReachedMilestone $a, ReachedMilestone $b): int => $a->reachedAt <=> $b->reachedAt);

        return $reached;
    }

    #[Override]
    public function eraseMember(ResourceReference $member): int
    {
        $count = \count($this->reached);
        $this->reached = array_filter($this->reached, static fn (ReachedMilestone $reached): bool => !$reached->subject->equals($member) && !$reached->member->equals($member));

        return $count - \count($this->reached);
    }

    /**
     * Empties the store: a long-running process, such as a worker that serves one request after another, calls it
     * between two of them, so that each starts as a new process would.
     */
    public function reset(): void
    {
        $this->reached = [];
    }
}
