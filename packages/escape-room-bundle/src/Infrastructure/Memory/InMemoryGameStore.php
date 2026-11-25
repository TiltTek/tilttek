<?php

declare(strict_types=1);

namespace Tilttek\EscapeRoomBundle\Infrastructure\Memory;

use DateTimeImmutable;
use Override;
use Phil\Resource\ResourceReference;
use Symfony\Contracts\Service\ResetInterface;
use Tilttek\EscapeRoomBundle\Application\Contract\GameStore;

/**
 * The games in the memory of the process, for tests; `reset()`, tagged `kernel.reset`, forgets them between two
 * requests of a worker.
 */
final class InMemoryGameStore implements GameStore, ResetInterface
{
    /** @var array<string, array{values: array<array-key, mixed>, deadline: ?DateTimeImmutable}> */
    private array $games = [];

    #[Override]
    public function save(string $id, array $values, ?DateTimeImmutable $deadline): void
    {
        $this->games[$id] = ['values' => $values, 'deadline' => $deadline];
    }

    #[Override]
    public function find(string $id): ?array
    {
        return $this->games[$id]['values'] ?? null;
    }

    #[Override]
    public function due(DateTimeImmutable $at, int $limit): array
    {
        $due = [];

        foreach ($this->games as $id => $game) {
            if ($game['deadline'] instanceof DateTimeImmutable && $game['deadline']->getTimestamp() <= $at->getTimestamp()) {
                $due[$id] = $game['deadline']->format('Uu');
            }
        }

        asort($due);

        return \array_slice(array_map(strval(...), array_keys($due)), 0, max(1, $limit));
    }

    #[Override]
    public function ofTeam(ResourceReference $team): array
    {
        $games = [];

        foreach ($this->games as $id => $game) {
            if (($game['values']['team'] ?? null) === $team->toString()) {
                $games[$id] = $game['values'];
            }
        }

        return $games;
    }

    #[Override]
    public function eraseTeam(ResourceReference $team, ResourceReference $pseudonym): int
    {
        $erased = 0;

        foreach ($this->ofTeam($team) as $id => $values) {
            $this->games[$id]['values'] = [...$values, 'team' => $pseudonym->toString(), 'public' => false];
            ++$erased;
        }

        return $erased;
    }

    #[Override]
    public function forget(string ...$ids): int
    {
        $known = array_intersect_key($this->games, array_flip($ids));
        $this->games = array_diff_key($this->games, $known);

        return \count($known);
    }

    #[Override]
    public function reset(): void
    {
        $this->games = [];
    }
}
