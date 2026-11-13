<?php

declare(strict_types=1);

namespace Tilttek\TableSafety\Contract;

use Phil\Resource\ResourceReference;
use Tilttek\TableSafety\Participation;

/**
 * Where each participant's answers at each table are kept, one participation per table and participant.
 */
interface ParticipationStore
{
    public function find(ResourceReference $table, ResourceReference $participant): ?Participation;

    /**
     * Keeps the participation, in the place of the one of its table and participant.
     */
    public function save(Participation $participation): void;

    public function remove(ResourceReference $table, ResourceReference $participant): void;

    /**
     * @return list<Participation> the table's participations, in no order that tells who answered first
     */
    public function ofTable(ResourceReference $table): array;

    /**
     * @return list<Participation> the participant's, at every table
     */
    public function ofParticipant(ResourceReference $participant): array;

    /**
     * Deletes the participant's participations at every table; the number deleted.
     */
    public function eraseParticipant(ResourceReference $participant): int;
}
