<?php

declare(strict_types=1);

namespace Tilttek\SessionPrep\Contract;

use Tilttek\SessionPrep\Exception\InvalidPreparation;
use Tilttek\SessionPrep\Exception\PreparationConflict;
use Tilttek\SessionPrep\Preparation;
use Tilttek\SessionPrep\PreparationQuery;

/**
 * Keeps the preparations, each at a version.
 */
interface PreparationStore
{
    /**
     * The preparation, or null when none has the identifier.
     */
    public function preparation(string $id): ?Preparation;

    /**
     * Keeps the preparation at the next version, and returns it. A preparation at version 0 is new; any other is kept
     * only when the store holds it at that version.
     *
     * @throws PreparationConflict when the preparation is new and the store has one of its identifier, or the store
     *                             holds it at another version; nothing is kept
     */
    public function save(Preparation $preparation): Preparation;

    /**
     * Forgets the preparation, when the store holds it at its version.
     *
     * @throws PreparationConflict when the store holds it at another version, or not at all; nothing is forgotten
     */
    public function delete(Preparation $preparation): void;

    /**
     * The preparations the query matches, by identifier; at most the limit.
     *
     * @param int $limit 1 or more
     *
     * @return iterable<Preparation>
     *
     * @throws InvalidPreparation when the limit is below 1
     */
    public function preparations(PreparationQuery $query, int $limit): iterable;
}
