<?php

declare(strict_types=1);

namespace Tilttek\Points;

use Tilttek\Points\Exception\InvalidPoints;

/**
 * The key that makes a movement written once: the same key written twice is one movement. The application gives the
 * key of what it decides — a spending, a transfer, a grant — from what makes it unique, `order:2026-0193:redeem`; the
 * library builds the keys of what it derives, which start with `#` — `#earn:games:game:812:player:44` —, a character
 * the application's keys cannot start with. From 1 to 400 bytes, without whitespace or control characters.
 */
final readonly class MovementKey
{
    public const int MAX_LENGTH = 400;

    private function __construct(
        public string $value,
    ) {
    }

    public static function fromString(string $value): self
    {
        if ('' === $value || \strlen($value) > self::MAX_LENGTH) {
            throw InvalidPoints::because(\sprintf('A movement key is 1 to %d bytes; this one is %d.', self::MAX_LENGTH, \strlen($value)));
        }

        if (1 === preg_match('/[\s\x00-\x1F\x7F]/', $value)) {
            throw InvalidPoints::because('A movement key cannot contain whitespace or control characters.');
        }

        return new self($value);
    }

    /**
     * The key of a movement the application decides: `#` cannot start it, since the library's own keys do.
     */
    public static function decided(string $value): self
    {
        if (str_starts_with($value, '#')) {
            throw InvalidPoints::because(\sprintf('"%s" starts with "#", which only the keys the library derives start with.', $value));
        }

        return self::fromString($value);
    }

    /**
     * The key the library derives: `#`, the reason and what it derives from, joined by colons.
     */
    public static function derived(string $reason, string $from): self
    {
        return self::fromString('#'.$reason.':'.$from);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
