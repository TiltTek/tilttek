<?php

declare(strict_types=1);

namespace Tilttek\Raffle\Store;

use Override;
use Tilttek\Raffle\Contract\RaffleStore;
use Tilttek\Raffle\Exception\RaffleConflict;
use Tilttek\Raffle\Raffle;

/**
 * The raffles and their entrants in memory, for tests and for a raffle that lives in one process.
 */
final class InMemoryRaffleStore implements RaffleStore
{
    /** @var array<string, Raffle> */
    private array $raffles = [];

    /** @var array<string, array<array-key, string>> the entrants of each raffle, by entrant */
    private array $entrants = [];

    #[Override]
    public function find(string $raffle): ?Raffle
    {
        return $this->raffles[$raffle] ?? null;
    }

    #[Override]
    public function add(Raffle $raffle): void
    {
        if (isset($this->raffles[$raffle->id])) {
            throw RaffleConflict::at($raffle->id, 0, $this->raffles[$raffle->id]->version);
        }

        $this->raffles[$raffle->id] = $raffle;
        $this->entrants[$raffle->id] = [];
    }

    #[Override]
    public function save(Raffle $raffle): void
    {
        $this->check($raffle);
        $this->raffles[$raffle->id] = $raffle;
    }

    #[Override]
    public function enter(Raffle $raffle, string $entrant): bool
    {
        $this->check($raffle);

        if (isset($this->entrants[$raffle->id][$entrant])) {
            return false;
        }

        $this->entrants[$raffle->id][$entrant] = $entrant;
        $this->raffles[$raffle->id] = $raffle;

        return true;
    }

    #[Override]
    public function entrants(string $raffle): array
    {
        return array_values($this->entrants[$raffle] ?? []);
    }

    private function check(Raffle $raffle): void
    {
        $stored = $this->raffles[$raffle->id] ?? null;

        if ($stored?->version !== $raffle->version - 1) {
            throw RaffleConflict::at($raffle->id, $raffle->version - 1, $stored?->version);
        }
    }
}
