<?php

declare(strict_types=1);

namespace Tilttek\TableRules\View;

use Tilttek\TableRules\AcceptanceState;

/**
 * Where the players of a table stand with its rules, to show the game master: each player's status, and how many are
 * in each state.
 */
final readonly class RosterView
{
    /**
     * @param ?int               $currentVersion the number of the version that holds, null before the first
     * @param list<PlayerStatus> $players        in the order asked
     */
    public function __construct(public string $table, public ?int $currentVersion, public array $players)
    {
    }

    /**
     * How many players are in the state.
     */
    public function count(AcceptanceState $state): int
    {
        return \count(array_filter($this->players, static fn (PlayerStatus $status): bool => $status->state === $state));
    }

    /**
     * Whether rules are published and every player asked accepted the ones that hold.
     */
    public function everyoneAccepted(): bool
    {
        return null !== $this->currentVersion && $this->count(AcceptanceState::Accepted) === \count($this->players);
    }

    /**
     * @return array{table: string, currentVersion: ?int, everyoneAccepted: bool, counts: array<string, int>, players: list<array<string, mixed>>}
     */
    public function toArray(): array
    {
        $counts = [];
        foreach (AcceptanceState::cases() as $state) {
            $counts[$state->value] = $this->count($state);
        }

        return [
            'table' => $this->table,
            'currentVersion' => $this->currentVersion,
            'everyoneAccepted' => $this->everyoneAccepted(),
            'counts' => $counts,
            'players' => array_map(static fn (PlayerStatus $status): array => $status->toArray(), $this->players),
        ];
    }
}
