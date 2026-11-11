<?php

declare(strict_types=1);

namespace Tilttek\Quests;

use DateTimeImmutable;
use DateTimeInterface;
use Phil\Resource\ResourceReference;

/**
 * A quest accomplished, in the journal of accomplished quests: the quest, its occurrence — empty for a quest open
 * once, the date an occurrence opens on for one that comes back, the duel's key for a duel —, who accomplished it —
 * the member, the team, or `community:<quest>` for a challenge of the community —, when, and by which activity.
 */
final readonly class Completion
{
    /**
     * The kind of the reference a challenge of the community is accomplished by.
     */
    public const string COMMUNITY = 'community';

    public function __construct(
        public string $quest,
        public string $occurrence,
        public ResourceReference $subject,
        public DateTimeImmutable $completedAt,
        public string $activity,
    ) {
    }

    /**
     * The reference a challenge of the community is accomplished by.
     */
    public static function community(string $quest): ResourceReference
    {
        return ResourceReference::of(self::COMMUNITY, $quest);
    }

    /**
     * @return array{quest: string, occurrence: string, subject: string, completed_at: string, activity: string}
     */
    public function toArray(): array
    {
        return [
            'quest' => $this->quest,
            'occurrence' => $this->occurrence,
            'subject' => $this->subject->toString(),
            'completed_at' => $this->completedAt->format(DateTimeInterface::ATOM),
            'activity' => $this->activity,
        ];
    }
}
