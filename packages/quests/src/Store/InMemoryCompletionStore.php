<?php

declare(strict_types=1);

namespace Tilttek\Quests\Store;

use DateTimeImmutable;
use Override;
use Phil\Resource\ResourceReference;
use Tilttek\Quests\Completion;

/**
 * The journal of accomplished quests in memory, forgotten with the process: for tests, scripts and examples.
 */
final class InMemoryCompletionStore implements CompletionStore
{
    /**
     * @var array<string, Completion>
     */
    private array $completions = [];

    #[Override]
    public function add(Completion $completion): bool
    {
        $key = $this->key($completion->quest, $completion->occurrence, $completion->subject);

        if (isset($this->completions[$key])) {
            return false;
        }

        $this->completions[$key] = $completion;

        return true;
    }

    #[Override]
    public function find(string $quest, string $occurrence, ResourceReference $subject): ?Completion
    {
        return $this->completions[$this->key($quest, $occurrence, $subject)] ?? null;
    }

    #[Override]
    public function latest(string $quest, ResourceReference $subject): ?Completion
    {
        $completions = array_filter($this->ofSubject($subject), static fn (Completion $completion): bool => $completion->quest === $quest);

        return [] === $completions ? null : end($completions);
    }

    #[Override]
    public function ofSubject(ResourceReference $subject): array
    {
        return $this->sorted(array_filter($this->completions, static fn (Completion $completion): bool => $completion->subject->equals($subject)));
    }

    #[Override]
    public function forget(?DateTimeImmutable $since): array
    {
        $forgotten = array_filter($this->completions, static fn (Completion $completion): bool => !$since instanceof DateTimeImmutable || $completion->completedAt >= $since);
        $this->completions = array_diff_key($this->completions, $forgotten);

        return $this->sorted($forgotten);
    }

    #[Override]
    public function eraseSubject(ResourceReference $subject): int
    {
        $count = \count($this->completions);
        $this->completions = array_filter($this->completions, static fn (Completion $completion): bool => !$completion->subject->equals($subject));

        return $count - \count($this->completions);
    }

    /**
     * Empties the store: a long-running process, such as a worker that serves one request after another, calls it
     * between two of them, so that each starts as a new process would.
     */
    public function reset(): void
    {
        $this->completions = [];
    }

    private function key(string $quest, string $occurrence, ResourceReference $subject): string
    {
        return json_encode([$quest, $occurrence, $subject->toString()], \JSON_THROW_ON_ERROR);
    }

    /**
     * @param array<string, Completion> $completions
     *
     * @return list<Completion>
     */
    private function sorted(array $completions): array
    {
        usort($completions, static fn (Completion $a, Completion $b): int => [$a->completedAt->getTimestamp(), $a->quest, $a->occurrence] <=> [$b->completedAt->getTimestamp(), $b->quest, $b->occurrence]);

        return $completions;
    }
}
