<?php

declare(strict_types=1);

namespace Tilttek\Quests\Store;

use Phil\Resource\ResourceReference;
use Tilttek\Quests\Enrolment;

/**
 * Where the members' acceptances and abandonments are kept: one enrolment per quest and member.
 */
interface EnrolmentStore
{
    /**
     * Keeps the enrolment, in place of the member's earlier one for the quest.
     */
    public function save(Enrolment $enrolment): void;

    public function find(string $quest, ResourceReference $member): ?Enrolment;

    /**
     * The enrolments of a quest, by member.
     *
     * @return list<Enrolment>
     */
    public function ofQuest(string $quest): array;

    /**
     * The enrolments of a member, by quest.
     *
     * @return list<Enrolment>
     */
    public function ofMember(ResourceReference $member): array;

    /**
     * @return int how many enrolments of the member were removed
     */
    public function eraseMember(ResourceReference $member): int;
}
