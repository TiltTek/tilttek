<?php

declare(strict_types=1);

namespace Tilttek\Handouts\Store;

use DateTimeImmutable;
use Tilttek\Handouts\Audience;
use Tilttek\Handouts\Contract\HandoutStore;
use Tilttek\Handouts\Disclosure;
use Tilttek\Handouts\Handout;
use Tilttek\Handouts\Sighting;
use Tilttek\Handouts\StagedReveal;

/**
 * The handouts kept in memory, for tests and for an application that rebuilds them at each start.
 */
final class InMemoryHandoutStore implements HandoutStore
{
    /** @var array<string, Handout> */
    private array $handouts = [];

    /** @var array<string, array<string, Disclosure>> by handout, then key */
    private array $disclosures = [];

    /** @var array<string, array<string, StagedReveal>> by handout, then key */
    private array $staged = [];

    /** @var array<string, array<string, Sighting>> by handout, then player */
    private array $sightings = [];

    public function handout(string $handout): ?Handout
    {
        return $this->handouts[$handout] ?? null;
    }

    public function saveHandout(Handout $handout): void
    {
        $this->handouts[$handout->reference->toString()] = $handout;
    }

    public function removeHandout(string $handout): void
    {
        unset($this->handouts[$handout], $this->disclosures[$handout], $this->staged[$handout], $this->sightings[$handout]);
    }

    public function handouts(string $table): array
    {
        $found = array_filter($this->handouts, static fn (Handout $handout): bool => $handout->table->toString() === $table);
        usort($found, static fn (Handout $a, Handout $b): int => self::order($a->createdAt, $b->createdAt, $a->reference->toString(), $b->reference->toString()));

        return $found;
    }

    public function countHandouts(string $table): int
    {
        return \count($this->handouts($table));
    }

    public function disclosure(string $handout, string $key): ?Disclosure
    {
        return $this->disclosures[$handout][$key] ?? null;
    }

    public function saveDisclosure(Disclosure $disclosure): void
    {
        $this->disclosures[$disclosure->handout->toString()][$disclosure->key()] = $disclosure;
    }

    public function removeDisclosure(string $handout, string $key): void
    {
        unset($this->disclosures[$handout][$key]);
    }

    public function disclosures(string $handout): array
    {
        return $this->sortDisclosures($this->disclosures[$handout] ?? []);
    }

    public function disclosuresFor(string $table, string $player): array
    {
        $found = [];

        foreach ($this->disclosures as $disclosures) {
            foreach ([Audience::EVERYONE, $player] as $key) {
                if (isset($disclosures[$key]) && $disclosures[$key]->table->toString() === $table) {
                    $found[] = $disclosures[$key];
                }
            }
        }

        return $this->sortDisclosures($found);
    }

    public function staged(string $handout, string $key): ?StagedReveal
    {
        return $this->staged[$handout][$key] ?? null;
    }

    public function saveStaged(StagedReveal $staged): void
    {
        $this->staged[$staged->handout->toString()][$staged->key()] = $staged;
    }

    public function removeStaged(string $handout, string $key): void
    {
        unset($this->staged[$handout][$key]);
    }

    public function stagedOf(string $handout): array
    {
        return $this->sortStaged($this->staged[$handout] ?? []);
    }

    public function due(DateTimeImmutable $until, int $limit): array
    {
        $found = [];

        foreach ($this->staged as $staged) {
            foreach ($staged as $reveal) {
                $at = $reveal->trigger->at;

                if (null !== $at && $at <= $until) {
                    $found[] = $reveal;
                }
            }
        }

        usort($found, static function (StagedReveal $a, StagedReveal $b): int {
            $byTime = $a->trigger->at <=> $b->trigger->at;

            return 0 !== $byTime ? $byTime : strcmp($a->handout->toString().' '.$a->key(), $b->handout->toString().' '.$b->key());
        });

        return \array_slice($found, 0, $limit);
    }

    public function onCue(string $table, string $cue, int $limit): array
    {
        $found = [];

        foreach ($this->staged as $staged) {
            foreach ($staged as $reveal) {
                if ($cue === $reveal->trigger->cue && $reveal->table->toString() === $table) {
                    $found[] = $reveal;
                }
            }
        }

        return \array_slice($this->sortStaged($found), 0, $limit);
    }

    public function sighting(string $handout, string $player): ?Sighting
    {
        return $this->sightings[$handout][$player] ?? null;
    }

    public function saveSighting(Sighting $sighting): void
    {
        $this->sightings[$sighting->handout->toString()][$sighting->player->toString()] = $sighting;
    }

    public function sightings(string $handout): array
    {
        $found = $this->sightings[$handout] ?? [];
        usort($found, static fn (Sighting $a, Sighting $b): int => self::order($a->firstSeenAt, $b->firstSeenAt, $a->player->toString(), $b->player->toString()));

        return $found;
    }

    public function forgetPlayer(string $player): array
    {
        $touched = [];

        foreach ([&$this->disclosures, &$this->staged, &$this->sightings] as &$entries) {
            foreach ($entries as $handout => $byKey) {
                if (isset($byKey[$player])) {
                    unset($entries[$handout][$player]);
                    $touched[$handout] = $handout;
                }
            }
        }

        unset($entries);

        sort($touched, \SORT_STRING);

        return $touched;
    }

    /**
     * @param array<Disclosure> $disclosures
     *
     * @return list<Disclosure>
     */
    private function sortDisclosures(array $disclosures): array
    {
        usort($disclosures, static fn (Disclosure $a, Disclosure $b): int => self::order($a->revealedAt, $b->revealedAt, $a->handout->toString().' '.$a->key(), $b->handout->toString().' '.$b->key()));

        return $disclosures;
    }

    /**
     * @param array<StagedReveal> $staged
     *
     * @return list<StagedReveal>
     */
    private function sortStaged(array $staged): array
    {
        usort($staged, static fn (StagedReveal $a, StagedReveal $b): int => self::order($a->stagedAt, $b->stagedAt, $a->handout->toString().' '.$a->key(), $b->handout->toString().' '.$b->key()));

        return $staged;
    }

    private static function order(DateTimeImmutable $a, DateTimeImmutable $b, string $tieA, string $tieB): int
    {
        $byTime = $a <=> $b;

        return 0 !== $byTime ? $byTime : strcmp($tieA, $tieB);
    }
}
