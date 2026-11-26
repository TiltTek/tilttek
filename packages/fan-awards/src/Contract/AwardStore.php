<?php

declare(strict_types=1);

namespace Tilttek\FanAwards\Contract;

use Tilttek\FanAwards\Edition;
use Tilttek\FanAwards\Nominee;
use Tilttek\FanAwards\Phase;
use Tilttek\FanAwards\PhaseCount;

/**
 * Where the editions, their nominees, their ballots and their counts are kept. Voters are named by their references
 * written `kind:identifier`; a ballot holds the keys of its nominees, in the voter's order.
 *
 * An implementation keeps no state between two requests beside its storage.
 */
interface AwardStore
{
    public function edition(string $key): ?Edition;

    /**
     * Writes the edition, replacing the one of the same key.
     */
    public function saveEdition(Edition $edition): void;

    /**
     * The nominees of the category in the phase, in the order they were added.
     *
     * @return list<Nominee>
     */
    public function candidates(string $edition, string $category, Phase $phase): array;

    public function addCandidate(string $edition, string $category, Phase $phase, Nominee $nominee): void;

    /**
     * The voter's ballot in the category and the phase, or null.
     *
     * @return ?list<string>
     */
    public function ballot(string $edition, string $category, Phase $phase, string $voter): ?array;

    /**
     * Writes the voter's ballot, replacing their earlier one in the category and the phase.
     *
     * @param list<string> $choices
     */
    public function saveBallot(string $edition, string $category, Phase $phase, string $voter, array $choices): void;

    /**
     * Every ballot of the category in the phase, those of erased voters included, read once by the count.
     *
     * @return iterable<list<string>>
     */
    public function ballots(string $edition, string $category, Phase $phase): iterable;

    public function saveCount(string $edition, string $category, PhaseCount $count): void;

    /**
     * The counts of the category, in the order of their phases.
     *
     * @return list<PhaseCount>
     */
    public function counts(string $edition, string $category): array;

    /**
     * Forgets the voter on every ballot they cast; the ballots stay, anonymous, in the counts.
     */
    public function forget(string $voter): void;
}
