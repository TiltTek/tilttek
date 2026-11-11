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
    public function get(string $id): ?RecordedRoll;

    public function save(RecordedRoll $roll): void;

    /**
     * The rolls of an owner, the latest first.
     *
     * @return list<RecordedRoll>
     */
    public function ofOwner(ResourceReference $owner, int $limit): array;

    /**
     * Erases every roll of an owner.
     *
     * @return int how many rolls were erased
     */
    public function eraseOwner(ResourceReference $owner): int;
}
