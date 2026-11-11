<?php

declare(strict_types=1);

namespace Tilttek\Activity;

use DateTimeImmutable;
use DateTimeInterface;
use Phil\Resource\ResourceReference;
use Tilttek\Activity\Exception\InvalidActivity;

/**
 * A fact: this member made this gesture, on this object, at this moment. The member and the object are opaque
 * references the application gives; the verb is the application's; the quantity says how much of the gesture — one
 * game, three pages, an order of 42 — and is at least 1; the context holds a few facts the rules may read. The
 * idempotency key makes the same gesture count once, however many times it is recorded.
 *
 * The moment falls between the years 1970 and 9999, in UTC; the journal narrows it to a window around its clock.
 *
 * An activity carries no text of the member: a comment is its object, by reference.
 */
final readonly class Activity
{
    public const int MAX_QUANTITY = 1_000_000_000;

    /**
     * The first moment an activity may happen at, 1970-01-01T00:00:00Z, as a Unix timestamp.
     */
    public const int EARLIEST = 0;

    /**
     * The last moment an activity may happen at, 9999-12-31T23:59:59Z, as a Unix timestamp.
     */
    public const int LATEST = 253_402_300_799;

    public function __construct(
        public IdempotencyKey $key,
        public ResourceReference $member,
        public Verb $verb,
        public DateTimeImmutable $occurredAt,
        public ?ResourceReference $object = null,
        public int $quantity = 1,
        public Context $context = new Context(),
    ) {
        $timestamp = $occurredAt->getTimestamp();

        if ($timestamp < self::EARLIEST || $timestamp > self::LATEST) {
            throw InvalidActivity::because(\sprintf('An activity happens between the years 1970 and 9999; this one is dated %s.', $occurredAt->format(DateTimeInterface::ATOM)));
        }

        if ($quantity < 1 || $quantity > self::MAX_QUANTITY) {
            throw InvalidActivity::because(\sprintf('The quantity of an activity is 1 to %d; this one is %d.', self::MAX_QUANTITY, $quantity));
        }
    }

    /**
     * An activity from plain values: the member and the object as `kind:identifier`, the verb and the key as strings.
     *
     * @param array<string, bool|int|string> $context
     */
    public static function of(string $key, string $member, string $verb, DateTimeImmutable $occurredAt, ?string $object = null, int $quantity = 1, array $context = []): self
    {
        return new self(
            IdempotencyKey::fromString($key),
            ResourceReference::fromString($member),
            Verb::fromString($verb),
            $occurredAt,
            null === $object ? null : ResourceReference::fromString($object),
            $quantity,
            new Context($context),
        );
    }

    /**
     * Whether the other activity is the same gesture: the same member, verb, object, quantity and context. The moment
     * is left out, since a task run again may stamp it again.
     */
    public function sameGestureAs(self $other): bool
    {
        return $this->member->equals($other->member)
            && $this->verb->equals($other->verb)
            && $this->object?->toString() === $other->object?->toString()
            && $this->quantity === $other->quantity
            && $this->context->equals($other->context);
    }

    /**
     * The same activity, its object forgotten: what is left of another member's gesture when the member it was about
     * is erased.
     */
    public function withoutObject(): self
    {
        return new self($this->key, $this->member, $this->verb, $this->occurredAt, null, $this->quantity, $this->context);
    }

    /**
     * The activity as plain values, for an export or a log.
     *
     * @return array{key: string, member: string, verb: string, occurred_at: string, object: ?string, quantity: int, context: array<string, bool|int|string>}
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key->value,
            'member' => $this->member->toString(),
            'verb' => $this->verb->value,
            'occurred_at' => $this->occurredAt->format(DateTimeInterface::ATOM),
            'object' => $this->object?->toString(),
            'quantity' => $this->quantity,
            'context' => $this->context->values,
        ];
    }
}
