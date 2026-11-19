<?php

declare(strict_types=1);

namespace Tilttek\DiceJail\Store;

use DateTimeImmutable;
use Override;
use Tilttek\DiceJail\Contract\RollSource;

/**
 * The rolls of each die in memory, for tests and for a table that lives in one process; `reset()` empties it between
 * the requests of a worker.
 */
final class InMemoryRollSource implements RollSource
{
    /** @var array<string, list<array{int, DateTimeImmutable}>> */
    private array $rolls = [];

    public function record(string $die, int $face, DateTimeImmutable $at): void
    {
        $this->rolls[$die][] = [$face, $at];
    }

    #[Override]
    public function faces(string $die, ?DateTimeImmutable $since = null): iterable
    {
        foreach ($this->rolls[$die] ?? [] as [$face, $at]) {
            if (!$since instanceof DateTimeImmutable || $at >= $since) {
                yield $face;
            }
        }
    }

    public function reset(): void
    {
        $this->rolls = [];
    }
}
