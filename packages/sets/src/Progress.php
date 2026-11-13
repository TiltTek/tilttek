<?php

declare(strict_types=1);

namespace Tilttek\Sets;

/**
 * Where a holder stands in a set: the evaluation of their holdings against its latest version, the completions kept
 * from any version, and those this call recorded.
 */
final readonly class Progress
{
    /**
     * @param list<Completion> $completions every completion of the holder in this set, the set's own and its tiers'
     * @param list<Completion> $recorded    the completions this call wrote, in the order they were told
     */
    public function __construct(
        public Evaluation $evaluation,
        public array $completions,
        public array $recorded = [],
    ) {
    }

    /**
     * The completion of the set itself, under whichever version it was earned, or null.
     */
    public function completion(): ?Completion
    {
        foreach ($this->completions as $completion) {
            if ($completion->isOfSet()) {
                return $completion;
            }
        }

        return null;
    }

    /**
     * Whether the holder completed the set once, under any version; a completion is never taken back.
     */
    public function completedOnce(): bool
    {
        return $this->completion() instanceof Completion;
    }

    /**
     * Whether the holder owns every member of the latest version right now.
     */
    public function isComplete(): bool
    {
        return $this->evaluation->isComplete();
    }

    /**
     * Whether the holder completed an earlier version and does not own every member of the latest one: the completion
     * stays, and the application may invite the holder to complete the new members.
     */
    public function completedUnderEarlierVersion(): bool
    {
        $completion = $this->completion();

        return $completion instanceof Completion && $completion->version < $this->evaluation->set->version && !$this->isComplete();
    }

    /**
     * Whether the holder reached the tier once, under any version.
     */
    public function tierReachedOnce(string $tier): bool
    {
        return array_any($this->completions, static fn (Completion $completion): bool => $tier === $completion->tier);
    }
}
