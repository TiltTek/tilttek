<?php

declare(strict_types=1);

namespace Tilttek\Progression\Store;

use Phil\Resource\ResourceReference;
use Tilttek\Progression\ReachedMilestone;

/**
 * Where the milestones reached are kept: once per track, milestone, subject and season. A milestone reached stays
 * reached — its reward is given —, whatever a cancellation or a rebuild does to the experience.
 */
interface MilestoneStore
{
    /**
     * Keeps the milestone reached, its notice due until `markDelivered()`; false when it was reached already.
     */
    public function reach(ReachedMilestone $reached): bool;

    /**
     * The subject's milestones whose notice is due, in the order they were reached.
     *
     * @return list<ReachedMilestone>
     */
    public function undelivered(ResourceReference $subject): array;

    /**
     * Notes that the observer was told of the milestone; nothing when the store does not hold it.
     */
    public function markDelivered(ReachedMilestone $reached): void;

    /**
     * The milestones the subject reached, in the order they were reached.
     *
     * @return list<ReachedMilestone>
     */
    public function of(ResourceReference $subject): array;

    /**
     * The milestones whose subject is the member and those the member's gestures reached for another subject — a
     * character —, in the order they were reached, for the member's export.
     *
     * @return list<ReachedMilestone>
     */
    public function ofMember(ResourceReference $member): array;

    /**
     * Removes the milestones whose subject is the member and those the member's gestures reached.
     *
     * @return int how many were removed
     */
    public function eraseMember(ResourceReference $member): int;
}
