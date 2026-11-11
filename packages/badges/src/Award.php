<?php

declare(strict_types=1);

namespace Tilttek\Badges;

use DateTimeImmutable;
use DateTimeInterface;
use Phil\Resource\ResourceReference;
use Tilttek\Badges\Exception\InvalidBadge;

/**
 * A badge a member holds: its number — 1 for the first award, 2 for the second of a repeatable badge —, the moment it
 * was earned — the moment of the activity that reached the target, or of the manual award —, its cause — the
 * activity's idempotency key, or the reason an administrator gave —, and whether it was awarded by hand.
 */
final readonly class Award
{
    public function __construct(
        public string $badge,
        public ResourceReference $member,
        public int $number,
        public DateTimeImmutable $awardedAt,
        public ?string $cause = null,
        public bool $manual = false,
    ) {
        if ($number < 1) {
            throw InvalidBadge::because(\sprintf('The number of an award starts at 1; %d is not one.', $number));
        }

        if (null !== $cause && ('' === $cause || \strlen($cause) > 255)) {
            throw InvalidBadge::because(\sprintf('The cause of an award is 1 to 255 bytes; this one is %d.', \strlen($cause)));
        }
    }

    /**
     * The award as plain values, for an export.
     *
     * @return array{badge: string, member: string, number: int, awarded_at: string, cause: ?string, manual: bool}
     */
    public function toArray(): array
    {
        return [
            'badge' => $this->badge,
            'member' => $this->member->toString(),
            'number' => $this->number,
            'awarded_at' => $this->awardedAt->format(DateTimeInterface::ATOM),
            'cause' => $this->cause,
            'manual' => $this->manual,
        ];
    }
}
