<?php

declare(strict_types=1);

namespace Tilttek\Raffle\Contract;

use Tilttek\Raffle\Exception\RaffleConflict;
use Tilttek\Raffle\Raffle;

/**
 * Where the raffles and their entrants are kept. Every change of a raffle stores its next version, and is refused
 * when the version stored is not the one it was made from: an entry that comes while the entries close never slips
 * past the hash of the entrants.
 */
interface RaffleStore
{
    public function find(string $raffle): ?Raffle;

    /**
     * Keeps a newly opened raffle, at version 1.
     *
     * @throws RaffleConflict when a raffle has its identifier already
     */
    public function add(Raffle $raffle): void;

    /**
     * Keeps the raffle's next version.
     *
     * @throws RaffleConflict when the version stored is not `$raffle->version - 1`
     */
    public function save(Raffle $raffle): void;

    /**
     * Adds the entrant and keeps the raffle's next version, both or neither.
     *
     * @return bool false, and nothing kept, when the entrant has entered already
     *
     * @throws RaffleConflict when the version stored is not `$raffle->version - 1`
     */
    public function enter(Raffle $raffle, string $entrant): bool;

    /**
     * The entrants of the raffle, in any order.
     *
     * @return list<string>
     */
    public function entrants(string $raffle): array;
}
