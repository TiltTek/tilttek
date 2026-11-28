<?php

declare(strict_types=1);

namespace Tilttek\ReferenceTables;

use Stringable;
use Tilttek\ReferenceTables\Exception\InvalidTable;

/**
 * The values a row of a table looked up by range covers, both ends included: `3–5`, `12`, `21+`, `≤0`, or `96–00` on a
 * d100, where `00` stands for 100.
 */
final readonly class Range implements Stringable
{
    /** The largest value a range covers, upwards and downwards; an open end reaches it. */
    public const int LIMIT = 1_000_000;

    /** The longest text a range is read from. */
    private const int MAX_TEXT = 32;

    /**
     * @throws InvalidTable when an end is beyond `LIMIT` or the range runs backwards
     */
    public function __construct(public int $min, public int $max)
    {
        if ($min < -self::LIMIT || $max > self::LIMIT) {
            throw InvalidTable::because(\sprintf('A range covers values from %d to %d only.', -self::LIMIT, self::LIMIT));
        }

        if ($max < $min) {
            throw InvalidTable::because(\sprintf('The range %d–%d runs backwards.', $min, $max));
        }
    }

    public static function of(int $value): self
    {
        return new self($value, $value);
    }

    /**
     * A range from its text: a value, `12`; two values joined by a hyphen, an en dash or an em dash, `3-5`, `3–5`,
     * `-3–-1`; a value and `+`, `21+`, open upwards; `≤` or `<=` and a value, `≤0`, open downwards. Spaces around the
     * parts are allowed.
     *
     * @param bool $percentile whether the table rolls a d100, on which `00` stands for 100
     *
     * @throws InvalidTable
     */
    public static function parse(string $text, bool $percentile = false): self
    {
        if (\strlen($text) > self::MAX_TEXT) {
            throw InvalidTable::because(\sprintf('A range is written in %d bytes at most.', self::MAX_TEXT));
        }

        $text = trim($text);

        if (1 === preg_match('/^(?:≤|<=)\s*(-?\d{1,7})$/', $text, $match)) {
            return new self(-self::LIMIT, self::value($match[1], $percentile));
        }

        if (1 === preg_match('/^(-?\d{1,7})\s*\+$/', $text, $match)) {
            return new self(self::value($match[1], $percentile), self::LIMIT);
        }

        if (1 === preg_match('/^(-?\d{1,7})(?:\s*(?:-|–|—)\s*(-?\d{1,7}))?$/', $text, $match)) {
            $min = self::value($match[1], $percentile);

            return new self($min, isset($match[2]) ? self::value($match[2], $percentile) : $min);
        }

        throw InvalidTable::because(\sprintf('"%s" is not a range such as "12", "3–5", "21+" or "≤0".', $text));
    }

    public function contains(int $value): bool
    {
        return $value >= $this->min && $value <= $this->max;
    }

    public function overlaps(self $other): bool
    {
        return $this->min <= $other->max && $other->min <= $this->max;
    }

    /**
     * The range as a table prints it: `12`, `3–5`, `21+`, `≤0`.
     */
    public function toString(): string
    {
        return match (true) {
            $this->min === $this->max => (string) $this->min,
            self::LIMIT === $this->max => $this->min.'+',
            -self::LIMIT === $this->min => '≤'.$this->max,
            default => $this->min.'–'.$this->max,
        };
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    private static function value(string $digits, bool $percentile): int
    {
        return $percentile && '00' === $digits ? 100 : (int) $digits;
    }
}
