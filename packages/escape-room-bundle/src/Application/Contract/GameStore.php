<?php

declare(strict_types=1);

namespace Tilttek\EscapeRoomBundle\Application\Contract;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;

/**
 * Where the games are kept, as the plain values `Game::toArray()` writes, with the moment the countdown of a running
 * game reaches zero.
 */
interface GameStore
{
    /**
     * Writes the game, or its new state.
     *
     * @param array<array-key, mixed> $values   the plain values `Game::toArray()` writes: the room, the team, the session…
     * @param ?DateTimeImmutable      $deadline when the countdown reaches zero; null while the game is paused or over
     */
    public function save(string $id, array $values, ?DateTimeImmutable $deadline): void;

    /**
     * @return ?array<array-key, mixed> the values written last, or null when there is no such game
     */
    public function find(string $id): ?array;

    /**
     * The games whose countdown reached zero at a moment, the earliest first.
     *
     * @param int $limit how many, 1 or more
     *
     * @return list<string>
     */
    public function due(DateTimeImmutable $at, int $limit): array;

    /**
     * @return array<string, array<array-key, mixed>> the games of a team, by identifier
     */
    public function ofTeam(ResourceReference $team): array;

    /**
     * Writes the pseudonym in place of the team in each of its games, and withdraws the consent to show their results.
     *
     * @return int the games changed
     */
    public function eraseTeam(ResourceReference $team, ResourceReference $pseudonym): int;

    /**
     * Deletes the games; an identifier with no game is passed over.
     *
     * @return int the games deleted
     */
    public function forget(string ...$ids): int;
}
