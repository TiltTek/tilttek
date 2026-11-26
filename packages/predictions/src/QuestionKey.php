<?php

declare(strict_types=1);

namespace Tilttek\Predictions;

use Tilttek\Predictions\Exception\InvalidPrediction;

/**
 * The key the application gives a question, from the event it is about — `final:2026`, `campaign:12:end` —: the same
 * key asked twice is one question. From 1 to 200 bytes, without whitespace or control characters, and not starting
 * with `#`, which the keys of `tilttek/points` derive.
 */
final readonly class QuestionKey
{
    public const int MAX_LENGTH = 200;

    private function __construct(
        public string $value,
    ) {
    }

    public static function fromString(string $value): self
    {
        if ('' === $value || \strlen($value) > self::MAX_LENGTH) {
            throw InvalidPrediction::because(\sprintf('A question key is 1 to %d bytes; this one is %d.', self::MAX_LENGTH, \strlen($value)));
        }

        if (1 === preg_match('/[\s\x00-\x1F\x7F]/', $value)) {
            throw InvalidPrediction::because('A question key cannot contain whitespace or control characters.');
        }

        if (str_starts_with($value, '#')) {
            throw InvalidPrediction::because(\sprintf('"%s" starts with "#", which only the keys the ledger derives start with.', $value));
        }

        return new self($value);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
