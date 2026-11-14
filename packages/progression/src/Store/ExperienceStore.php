<?php

declare(strict_types=1);

namespace Tilttek\Progression\Store;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use Tilttek\Progression\Experience\Gain;

/**
 * Where the experience is kept: one gain per track, rule and activity, written once even when the journal hands the
 * activity twice, and never spent; each gain with whether the observer was told what it brought.
 */
interface ExperienceStore
{
    /**
     * Keeps the gain, its notice due until `markDelivered()`; false when the activity already brought one to this track
     * by this rule.
     */
    public function add(Gain $gain): bool;

    /**
     * The subject's gains whose notice is due, in the order they occurred.
     *
     * @return list<Gain>
     */
    public function undelivered(ResourceReference $subject): array;

    /**
     * Notes that the observer was told what the gain brought; nothing when the store does not hold it.
     */
    public function markDelivered(Gain $gain): void;

    /**
     * Removes every gain of the activity, for its cancellation, and returns them.
     *
     * @return list<Gain>
     */
    public function remove(string $activity): array;

    /**
     * The subject's experience on the track in the season — the empty code for no season.
     */
    public function total(string $track, ResourceReference $subject, string $season): int;

    /**
     * The subject's experience on the track, every season together.
     */
    public function lifetime(string $track, ResourceReference $subject): int;

    /**
     * The subject's experience on the track by season, those outside any season left out.
     *
     * @return list<Total>
     */
    public function seasons(string $track, ResourceReference $subject): array;

    /**
     * The experience the rule gave the subject from `since`, included, to `until`, excluded — ever when both are null.
     */
    public function earned(string $track, string $rule, ResourceReference $subject, ?DateTimeImmutable $since, ?DateTimeImmutable $until): int;

    /**
     * The totals by track, subject and season of every subject with a gain from `since` on — every one when `since`
     * is null —, read before a rebuild forgets them.
     *
     * @return list<Total>
     */
    public function totalsSince(?DateTimeImmutable $since): array;

    /**
     * The gains of the subject, in the order they occurred, for an export.
     *
     * @return list<Gain>
     */
    public function of(ResourceReference $subject): array;

    /**
     * The gains whose subject is the member and those the member's gestures brought to another subject — a character
     * —, in the order they occurred, for the member's export.
     *
     * @return list<Gain>
     */
    public function ofMember(ResourceReference $member): array;

    /**
     * Forgets the gains of the activities from `since` on — all of them when `since` is null —, before a rebuild.
     *
     * @return int how many were forgotten
     */
    public function forget(?DateTimeImmutable $since): int;

    /**
     * Removes the gains whose subject is the member and those the member's gestures brought.
     *
     * @return int how many were removed
     */
    public function eraseMember(ResourceReference $member): int;
}
