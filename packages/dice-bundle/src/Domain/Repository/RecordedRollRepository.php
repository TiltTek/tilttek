<?php

declare(strict_types=1);

namespace Tilttek\DiceBundle\Domain\Repository;

use Phil\Resource\ResourceReference;
use Tilttek\DiceBundle\Domain\Model\RecordedRoll;

/**
 * The journal of replayable rolls.
 */
interface RecordedRollRepository
{
    /**
     * How many identifiers `getMany()` reads at once, at most.
     */
    public const int MANY = 500;

    public function get(string $id): ?RecordedRoll;

    /**
     * The rolls of these identifiers the journal holds, in the order asked, each once; an unknown identifier is
     * skipped. The caller passes at most `MANY` identifiers.
     *
     * @param list<string> $ids
     *
     * @return list<RecordedRoll>
     */
    public function getMany(array $ids): array;

    public function save(RecordedRoll $roll): void;

    /**
     * The rolls of an owner, the latest first.
     *
     * @return list<RecordedRoll>
     */
    public function ofOwner(ResourceReference $owner, int $limit): array;

    /**
     * The rolls made in a context — a table, a game —, the latest first.
     *
     * @return list<RecordedRoll>
     */
    public function ofContext(ResourceReference $context, int $limit): array;

    /**
     * Erases every roll of an owner.
     *
     * @return int how many rolls were erased
     */
    public function eraseOwner(ResourceReference $owner): int;
}
