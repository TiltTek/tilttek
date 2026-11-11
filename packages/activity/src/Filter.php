<?php

declare(strict_types=1);

namespace Tilttek\Activity;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;

/**
 * Which activities to read or count: a member, verbs by pattern, an object, and a period on the moment of the
 * activity — from `since`, included, to `until`, excluded. A filter left empty matches everything. It is immutable:
 * each method returns a new filter.
 */
final readonly class Filter
{
    private function __construct(
        public ?ResourceReference $member = null,
        public Verbs $verbs = new Verbs(),
        public ?ResourceReference $object = null,
        public ?DateTimeImmutable $since = null,
        public ?DateTimeImmutable $until = null,
    ) {
    }

    public static function all(): self
    {
        return new self();
    }

    public function member(ResourceReference $member): self
    {
        return new self($member, $this->verbs, $this->object, $this->since, $this->until);
    }

    /**
     * Only these verbs, as patterns such as `game.played` or `game.*`.
     */
    public function verbs(string ...$patterns): self
    {
        return new self($this->member, Verbs::of($patterns), $this->object, $this->since, $this->until);
    }

    public function object(ResourceReference $object): self
    {
        return new self($this->member, $this->verbs, $object, $this->since, $this->until);
    }

    public function since(DateTimeImmutable $since): self
    {
        return new self($this->member, $this->verbs, $this->object, $since, $this->until);
    }

    public function until(DateTimeImmutable $until): self
    {
        return new self($this->member, $this->verbs, $this->object, $this->since, $until);
    }

    public function matches(Activity $activity): bool
    {
        return (!$this->member instanceof ResourceReference || $this->member->equals($activity->member))
            && $this->verbs->allows($activity->verb)
            && (!$this->object instanceof ResourceReference || $this->object->toString() === $activity->object?->toString())
            && (!$this->since instanceof DateTimeImmutable || $activity->occurredAt >= $this->since)
            && (!$this->until instanceof DateTimeImmutable || $activity->occurredAt < $this->until);
    }
}
