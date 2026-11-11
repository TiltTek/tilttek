<?php

declare(strict_types=1);

namespace Tilttek\Quests;

use DateTimeImmutable;
use DateTimeInterface;
use Phil\Resource\ResourceReference;

/**
 * A member's decision about a quest: when they accepted it — under which team, for a team quest — and when they
 * abandoned it, if they did. A quest followed automatically has an enrolment only once the member abandons it.
 */
final readonly class Enrolment
{
    public function __construct(
        public string $quest,
        public ResourceReference $member,
        public DateTimeImmutable $acceptedAt,
        public ?ResourceReference $team = null,
        public ?DateTimeImmutable $abandonedAt = null,
    ) {
    }

    public function isAbandoned(): bool
    {
        return $this->abandonedAt instanceof DateTimeImmutable;
    }

    /**
     * Whether the member joined the quest under this team.
     */
    public function isUnder(ResourceReference $team): bool
    {
        return true === $this->team?->equals($team);
    }

    public function abandon(DateTimeImmutable $at): self
    {
        return new self($this->quest, $this->member, $this->acceptedAt, $this->team, $at);
    }

    /**
     * The time the member's activity counts for the quest: from their acceptance — from always, for a quest followed
     * automatically — until they abandoned it.
     */
    public function window(Quest $quest): Window
    {
        return new Window($quest->needsAcceptance() ? $this->acceptedAt : null, $this->abandonedAt);
    }

    /**
     * @return array{quest: string, member: string, accepted_at: string, team: ?string, abandoned_at: ?string}
     */
    public function toArray(): array
    {
        return [
            'quest' => $this->quest,
            'member' => $this->member->toString(),
            'accepted_at' => $this->acceptedAt->format(DateTimeInterface::ATOM),
            'team' => $this->team?->toString(),
            'abandoned_at' => $this->abandonedAt?->format(DateTimeInterface::ATOM),
        ];
    }
}
