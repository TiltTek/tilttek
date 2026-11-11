<?php

declare(strict_types=1);

namespace Tilttek\Progression\Experience;

use DateTimeImmutable;
use DateTimeInterface;
use Phil\Resource\ResourceReference;

/**
 * Experience an activity brought a subject on a track, by one rule: the subject is the member who made the gesture —
 * or its object, a character, on a track that follows objects —, the member is who made it, and the season is the code
 * of the season the activity fell in, empty outside any season. One gain per track, rule and activity.
 */
final readonly class Gain
{
    public function __construct(
        public string $track,
        public string $rule,
        public ResourceReference $subject,
        public ResourceReference $member,
        public string $activity,
        public int $amount,
        public DateTimeImmutable $occurredAt,
        public string $season = '',
    ) {
    }

    /**
     * The gain as plain values, for an export.
     *
     * @return array{track: string, rule: string, subject: string, member: string, activity: string, amount: int, occurred_at: string, season: string}
     */
    public function toArray(): array
    {
        return [
            'track' => $this->track,
            'rule' => $this->rule,
            'subject' => $this->subject->toString(),
            'member' => $this->member->toString(),
            'activity' => $this->activity,
            'amount' => $this->amount,
            'occurred_at' => $this->occurredAt->format(DateTimeInterface::ATOM),
            'season' => $this->season,
        ];
    }
}
