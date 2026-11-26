<?php

declare(strict_types=1);

namespace Tilttek\Handouts\Contract;

use DateTimeImmutable;
use Tilttek\Handouts\Disclosure;
use Tilttek\Handouts\Handout;
use Tilttek\Handouts\Sighting;
use Tilttek\Handouts\StagedReveal;

/**
 * Where handouts, their disclosures, their staged reveals and their sightings are kept. Handouts, tables and players
 * are named by their references written `kind:identifier`; an audience by its key, `*` for everyone or a player's
 * reference.
 *
 * Moments that tie are read in the byte order of the handout's reference, then of the key or the player.
 */
interface HandoutStore
{
    public function handout(string $handout): ?Handout;

    /**
     * Keeps the handout, replacing the one under the same reference.
     */
    public function saveHandout(Handout $handout): void;

    /**
     * Removes the handout with its disclosures, staged reveals and sightings.
     */
    public function removeHandout(string $handout): void;

    /**
     * The handouts of a table, the earliest created first.
     *
     * @return list<Handout>
     */
    public function handouts(string $table): array;

    public function countHandouts(string $table): int;

    public function disclosure(string $handout, string $key): ?Disclosure;

    /**
     * Keeps the disclosure, replacing the one of the same handout and key.
     */
    public function saveDisclosure(Disclosure $disclosure): void;

    public function removeDisclosure(string $handout, string $key): void;

    /**
     * The disclosures of a handout, the earliest revealed first.
     *
     * @return list<Disclosure>
     */
    public function disclosures(string $handout): array;

    /**
     * The disclosures of a table's handouts to everyone or to the player, the earliest revealed first.
     *
     * @return list<Disclosure>
     */
    public function disclosuresFor(string $table, string $player): array;

    public function staged(string $handout, string $key): ?StagedReveal;

    /**
     * Keeps the staged reveal, replacing the one of the same handout and key.
     */
    public function saveStaged(StagedReveal $staged): void;

    public function removeStaged(string $handout, string $key): void;

    /**
     * The staged reveals of a handout, the earliest staged first.
     *
     * @return list<StagedReveal>
     */
    public function stagedOf(string $handout): array;

    /**
     * At most `$limit` — 1 or more — staged reveals of every table whose moment is `$until` or earlier, the earliest
     * moment first.
     *
     * @return list<StagedReveal>
     */
    public function due(DateTimeImmutable $until, int $limit): array;

    /**
     * At most `$limit` — 1 or more — staged reveals of a table's handouts on the cue, the earliest staged first.
     *
     * @return list<StagedReveal>
     */
    public function onCue(string $table, string $cue, int $limit): array;

    public function sighting(string $handout, string $player): ?Sighting;

    /**
     * Keeps the sighting, replacing the one of the same handout and player.
     */
    public function saveSighting(Sighting $sighting): void;

    /**
     * The sightings of a handout, the earliest first seen first.
     *
     * @return list<Sighting>
     */
    public function sightings(string $handout): array;

    /**
     * Removes the player's disclosures, staged reveals and sightings in every table, and gives the references of the
     * handouts that held any, in byte order.
     *
     * @return list<string>
     */
    public function forgetPlayer(string $player): array;
}
