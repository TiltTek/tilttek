<?php

declare(strict_types=1);

namespace Tilttek\TableSafetyBundle\Infrastructure\Doctrine;

use DateTimeImmutable;
use DateTimeZone;
use UnexpectedValueException;

/**
 * What the stores share: the reading of the values a row holds. Dates are written in UTC, to the second; answers and
 * settings as JSON objects.
 */
final class Rows
{
    private function __construct()
    {
    }

    public static function utc(DateTimeImmutable $date): DateTimeImmutable
    {
        return $date->setTimezone(new DateTimeZone('UTC'));
    }

    /**
     * The microseconds since the epoch of a moment, which orders the moments of one second.
     */
    public static function microseconds(DateTimeImmutable $date): int
    {
        return (int) $date->format('Uu');
    }

    public static function date(mixed $value): DateTimeImmutable
    {
        return new DateTimeImmutable(self::text($value), new DateTimeZone('UTC'));
    }

    public static function text(mixed $value): string
    {
        if (!\is_string($value)) {
            throw new UnexpectedValueException(\sprintf('A text column holds %s.', get_debug_type($value)));
        }

        return $value;
    }

    /**
     * @param array<array-key, mixed> $value
     */
    public static function json(array $value): string
    {
        return json_encode((object) $value, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE | \JSON_PRESERVE_ZERO_FRACTION);
    }

    /**
     * @return array<array-key, mixed>
     */
    public static function decode(mixed $value): array
    {
        $decoded = json_decode(self::text($value), true, 32, \JSON_THROW_ON_ERROR);

        if (!\is_array($decoded)) {
            throw new UnexpectedValueException('A JSON column holds no object.');
        }

        return $decoded;
    }
}
