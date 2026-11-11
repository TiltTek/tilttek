<?php

declare(strict_types=1);

namespace Tilttek\DiceBundle\Infrastructure\Memory;

use Override;
use Phil\Resource\ResourceReference;
use Tilttek\DiceBundle\Domain\Model\RecordedRoll;
use Tilttek\DiceBundle\Domain\Repository\RecordedRollRepository;

/**
 * The journal in memory, for tests and for applications that keep no roll beyond the request.
 */
final class InMemoryRecordedRollRepository implements RecordedRollRepository
{
    /**
     * @var array<string, RecordedRoll>
     */
    private array $rolls = [];

    #[Override]
    public function get(string $id): ?RecordedRoll
    {
        return $this->rolls[$id] ?? null;
    }

    #[Override]
    public function save(RecordedRoll $roll): void
    {
        $this->rolls[$roll->id()] = $roll;
    }

    #[Override]
    public function ofOwner(ResourceReference $owner, int $limit): array
    {
        $rolls = $this->owned($owner);
        usort($rolls, static fn (RecordedRoll $a, RecordedRoll $b): int => [$b->rolledAt()->getTimestamp(), $a->id()] <=> [$a->rolledAt()->getTimestamp(), $b->id()]);

        return \array_slice($rolls, 0, $limit);
    }

    #[Override]
    public function eraseOwner(ResourceReference $owner): int
    {
        $rolls = $this->owned($owner);

        foreach ($rolls as $roll) {
            unset($this->rolls[$roll->id()]);
        }

        return \count($rolls);
    }

    /**
     * Empties the journal: a long-running process, such as a worker that serves one request after another, calls it
     * between two of them, so that each starts as a new process would.
     */
    public function reset(): void
    {
        $this->rolls = [];
    }

    /**
     * @return array<string, RecordedRoll>
     */
    private function owned(ResourceReference $owner): array
    {
        return array_filter($this->rolls, static fn (RecordedRoll $roll): bool => true === $roll->owner()?->equals($owner));
    }
}
