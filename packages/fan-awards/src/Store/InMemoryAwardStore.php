<?php

declare(strict_types=1);

namespace Tilttek\FanAwards\Store;

use Tilttek\FanAwards\Contract\AwardStore;
use Tilttek\FanAwards\Edition;
use Tilttek\FanAwards\Nominee;
use Tilttek\FanAwards\Phase;
use Tilttek\FanAwards\PhaseCount;

/**
 * The editions kept in memory, for tests and for an application that rebuilds them at each start.
 */
final class InMemoryAwardStore implements AwardStore
{
    /**
     * @var array<string, Edition>
     */
    private array $editions = [];

    /**
     * @var array<string, list<Nominee>> by edition, category and phase
     */
    private array $candidates = [];

    /**
     * @var array<string, array<string, list<string>>> by edition, category and phase, then voter
     */
    private array $ballots = [];

    /**
     * @var array<string, list<list<string>>> the ballots of erased voters, by edition, category and phase
     */
    private array $anonymous = [];

    /**
     * @var array<string, array<string, PhaseCount>> by edition and category, then phase
     */
    private array $counts = [];

    public function edition(string $key): ?Edition
    {
        return $this->editions[$key] ?? null;
    }

    public function saveEdition(Edition $edition): void
    {
        $this->editions[$edition->key] = $edition;
    }

    public function candidates(string $edition, string $category, Phase $phase): array
    {
        return $this->candidates[$this->slot($edition, $category, $phase)] ?? [];
    }

    public function addCandidate(string $edition, string $category, Phase $phase, Nominee $nominee): void
    {
        $this->candidates[$this->slot($edition, $category, $phase)][] = $nominee;
    }

    public function ballot(string $edition, string $category, Phase $phase, string $voter): ?array
    {
        return $this->ballots[$this->slot($edition, $category, $phase)][$voter] ?? null;
    }

    public function saveBallot(string $edition, string $category, Phase $phase, string $voter, array $choices): void
    {
        $this->ballots[$this->slot($edition, $category, $phase)][$voter] = $choices;
    }

    public function ballots(string $edition, string $category, Phase $phase): iterable
    {
        $slot = $this->slot($edition, $category, $phase);

        foreach ($this->ballots[$slot] ?? [] as $choices) {
            yield $choices;
        }

        foreach ($this->anonymous[$slot] ?? [] as $choices) {
            yield $choices;
        }
    }

    public function saveCount(string $edition, string $category, PhaseCount $count): void
    {
        $this->counts[$edition."\0".$category][$count->phase->value] = $count;
    }

    public function counts(string $edition, string $category): array
    {
        $counts = $this->counts[$edition."\0".$category] ?? [];
        $ordered = [];

        foreach (Phase::cases() as $phase) {
            if (isset($counts[$phase->value])) {
                $ordered[] = $counts[$phase->value];
            }
        }

        return $ordered;
    }

    public function forget(string $voter): void
    {
        foreach ($this->ballots as $slot => $ballots) {
            if (isset($ballots[$voter])) {
                $this->anonymous[$slot][] = $ballots[$voter];
                unset($this->ballots[$slot][$voter]);
            }
        }
    }

    private function slot(string $edition, string $category, Phase $phase): string
    {
        return $edition."\0".$category."\0".$phase->value;
    }
}
