<?php

declare(strict_types=1);

namespace Tilttek\Quests;

use DateTimeImmutable;
use DateTimeInterface;
use Phil\Resource\ResourceReference;

/**
 * What one activity brings to one step of a quest: the quest, the step, the member, the activity's idempotency key,
 * the value it counts — one, or its quantity for a total — and its moment. A quest's progress is read from its
 * contributions, never from a counter written in place: a cancellation removes the activity's contributions, and a
 * rebuild forgets those of a period before the journal hands them again.
 */
final readonly class Contribution
{
    public function __construct(
        public string $quest,
        public string $step,
        public ResourceReference $member,
        public string $activity,
        public int $value,
        public DateTimeImmutable $occurredAt,
    ) {
    }

    /**
     * @return array{quest: string, step: string, member: string, activity: string, value: int, occurred_at: string}
     */
    public function toArray(): array
    {
        return [
            'quest' => $this->quest,
            'step' => $this->step,
            'member' => $this->member->toString(),
            'activity' => $this->activity,
            'value' => $this->value,
            'occurred_at' => $this->occurredAt->format(DateTimeInterface::ATOM),
        ];
    }
}
