<?php

declare(strict_types=1);

namespace Tilttek\Exchange;

use Tilttek\Exchange\Exception\InvalidExchange;

/**
 * The key the application gives a reservation, a transfer or an offer, from what makes it unique —
 * `order:2026-0193:reward`, `gift:2026-11-07:44` —: the same key given twice is one exchange. From 1 to 200 bytes,
 * without whitespace or control characters, and not starting with `#`, which the keys of `tilttek/points` derive.
 */
final readonly class ExchangeKey
{
    public const int MAX_LENGTH = 200;

    private function __construct(
        public string $value,
    ) {
    }

    public static function fromString(string $value): self
    {
        if ('' === $value || \strlen($value) > self::MAX_LENGTH) {
            throw InvalidExchange::because(\sprintf('An exchange key is 1 to %d bytes; this one is %d.', self::MAX_LENGTH, \strlen($value)));
        }

        if (1 === preg_match('/[\s\x00-\x1F\x7F]/', $value)) {
            throw InvalidExchange::because('An exchange key cannot contain whitespace or control characters.');
        }

        if (str_starts_with($value, '#')) {
            throw InvalidExchange::because(\sprintf('"%s" starts with "#", which only the keys the ledger derives start with.', $value));
        }

        return new self($value);
    }
}
