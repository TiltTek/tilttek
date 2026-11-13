<?php

declare(strict_types=1);

namespace Tilttek\Stickers;

use DateTimeImmutable;
use DateTimeZone;
use Phil\Resource\ResourceReference;

/**
 * A sticker a holder placed on a surface — their profile, one of their messages, any resource the application names —:
 * where and how it sits, when it was placed in UTC, and the reason moderation hid it for, when it did. A hidden
 * placement is shown to no one until moderation restores it.
 */
final readonly class Placement
{
    public DateTimeImmutable $placedAt;

    public function __construct(
        public string $id,
        public ResourceReference $holder,
        public string $sticker,
        public ResourceReference $surface,
        public Transform $transform,
        DateTimeImmutable $placedAt,
        public ?string $hiddenFor = null,
    ) {
        Guard::reference($holder, 'holder');
        Guard::reference($surface, 'surface');
        Guard::code($sticker, 'sticker');

        if (null !== $hiddenFor) {
            Guard::code($hiddenFor, 'moderation reason');
        }

        $this->placedAt = $placedAt->setTimezone(new DateTimeZone('UTC'));
    }

    public function isHidden(): bool
    {
        return null !== $this->hiddenFor;
    }

    public function movedTo(Transform $transform): self
    {
        return new self($this->id, $this->holder, $this->sticker, $this->surface, $transform, $this->placedAt, $this->hiddenFor);
    }

    public function hiddenFor(string $reason): self
    {
        return new self($this->id, $this->holder, $this->sticker, $this->surface, $this->transform, $this->placedAt, $reason);
    }

    public function restored(): self
    {
        return new self($this->id, $this->holder, $this->sticker, $this->surface, $this->transform, $this->placedAt);
    }
}
