<?php

declare(strict_types=1);

namespace Tilttek\Collections;

use DateTimeImmutable;
use DateTimeInterface;
use Phil\Resource\ResourceReference;

/**
 * The first time a member owned every item of a set: written once, so that the set's rewards are told once, even when
 * the member trades an item away and gets it back.
 */
final readonly class SetCompletion
{
    public function __construct(
        public string $set,
        public ResourceReference $member,
        public DateTimeImmutable $completedAt,
    ) {
    }

    /**
     * @return array{set: string, member: string, completed_at: string}
     */
    public function toArray(): array
    {
        return ['set' => $this->set, 'member' => $this->member->toString(), 'completed_at' => $this->completedAt->format(DateTimeInterface::ATOM)];
    }
}
