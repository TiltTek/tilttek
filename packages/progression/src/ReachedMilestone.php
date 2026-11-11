<?php

declare(strict_types=1);

namespace Tilttek\Progression;

use DateTimeImmutable;
use DateTimeInterface;
use Phil\Resource\ResourceReference;

/**
 * A milestone a subject reached on a track in a season — the empty code for no season —, by the gesture of a member.
 */
final readonly class ReachedMilestone
{
    public function __construct(
        public string $track,
        public string $milestone,
        public ResourceReference $subject,
        public ResourceReference $member,
        public string $season,
        public DateTimeImmutable $reachedAt,
        public string $activity,
    ) {
    }

    /**
     * @return array{track: string, milestone: string, subject: string, member: string, season: string, reached_at: string, activity: string}
     */
    public function toArray(): array
    {
        return [
            'track' => $this->track,
            'milestone' => $this->milestone,
            'subject' => $this->subject->toString(),
            'member' => $this->member->toString(),
            'season' => $this->season,
            'reached_at' => $this->reachedAt->format(DateTimeInterface::ATOM),
            'activity' => $this->activity,
        ];
    }
}
