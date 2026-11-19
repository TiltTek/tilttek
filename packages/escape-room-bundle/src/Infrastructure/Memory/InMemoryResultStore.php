<?php

declare(strict_types=1);

namespace Tilttek\EscapeRoomBundle\Infrastructure\Memory;

use Override;
use Phil\Resource\ResourceReference;
use Symfony\Contracts\Service\ResetInterface;
use Tilttek\EscapeRoom\RoomResult;
use Tilttek\EscapeRoomBundle\Application\Contract\ResultStore;

/**
 * The results in the memory of the process, for tests; `reset()`, tagged `kernel.reset`, forgets them between two
 * requests of a worker.
 */
final class InMemoryResultStore implements ResultStore, ResetInterface
{
    /** @var array<string, RoomResult> */
    private array $results = [];

    #[Override]
    public function record(string $game, RoomResult $result): void
    {
        $this->results[$game] = $result;
    }

    #[Override]
    public function consent(string $game, bool $public): void
    {
        $result = $this->results[$game] ?? null;

        if ($result instanceof RoomResult) {
            $this->results[$game] = $this->with($result, $result->team, $public);
        }
    }

    #[Override]
    public function shown(string $room, int $limit): array
    {
        $shown = array_filter($this->results, static fn (RoomResult $result): bool => $result->public && $result->room === $room);
        usort($shown, static fn (RoomResult $a, RoomResult $b): int => [$a->finalSeconds, $a->hints, $a->escapedAt->format('Uu')] <=> [$b->finalSeconds, $b->hints, $b->escapedAt->format('Uu')]);

        return \array_slice($shown, 0, max(1, $limit));
    }

    #[Override]
    public function ofTeam(ResourceReference $team): array
    {
        return array_filter($this->results, static fn (RoomResult $result): bool => $result->team->equals($team));
    }

    #[Override]
    public function eraseTeam(ResourceReference $team, ResourceReference $pseudonym): int
    {
        $results = $this->ofTeam($team);

        foreach ($results as $game => $result) {
            $this->results[$game] = $this->with($result, $pseudonym, false);
        }

        return \count($results);
    }

    #[Override]
    public function reset(): void
    {
        $this->results = [];
    }

    private function with(RoomResult $result, ResourceReference $team, bool $public): RoomResult
    {
        return new RoomResult($result->room, $team, $result->session, $result->finalSeconds, $result->playedSeconds, $result->penaltySeconds, $result->hints, $result->skips, $result->escapedAt, $public);
    }
}
