<?php

declare(strict_types=1);

namespace Tilttek\Activity;

use Tilttek\Activity\Exception\InvalidActivity;

/**
 * The key the application gives a gesture so that it counts once: the same key recorded twice is one activity. It is
 * built from what makes the gesture unique — `comment:4512`, `order:2026-0193:paid` — never from the time it is
 * recorded, or a listener that runs twice would make two keys. From 1 to 255 bytes, without whitespace or control
 * characters.
 */
final readonly class IdempotencyKey
{
    public const int MAX_LENGTH = 255;

    private function __construct(
        public string $value,
    ) {
    }

    public static function fromString(string $value): self
    {
        if ('' === $value || \strlen($value) > self::MAX_LENGTH) {
            throw InvalidActivity::because(\sprintf('An idempotency key is 1 to %d bytes; this one is %d.', self::MAX_LENGTH, \strlen($value)));
        }

        if (1 === preg_match('/[\s\x00-\x1F\x7F]/', $value)) {
            throw InvalidActivity::because('An idempotency key cannot contain whitespace or control characters.');
        }

        return new self($value);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
