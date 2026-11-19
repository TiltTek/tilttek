<?php

declare(strict_types=1);

namespace Tilttek\ExchangeBundle\Infrastructure\Doctrine;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Phil\Resource\ResourceReference;
use UnexpectedValueException;

/**
 * What the stores share: the insertion that writes nothing when its unique key is already there, and the columns read
 * back as the values they hold. Moments are written by the type `datetime_utc_immutable`, which stores them in UTC,
 * to the second, and are read back in UTC.
 */
final class Rows
{
    private function __construct()
    {
    }

    /**
     * `INSERT` followed by `$into`, written to do nothing when the unique columns are already there: `ON CONFLICT DO
     * NOTHING` on PostgreSQL, `INSERT IGNORE` on MySQL and MariaDB, `INSERT OR IGNORE` on SQLite; a plain insertion
     * elsewhere, whose unique violation reaches the caller.
     */
    public static function ignoring(AbstractPlatform $platform, string $into, string $unique): string
    {
        return match (true) {
            $platform instanceof PostgreSQLPlatform => 'INSERT '.$into.' ON CONFLICT ('.$unique.') DO NOTHING',
            $platform instanceof AbstractMySQLPlatform => 'INSERT IGNORE '.$into,
            $platform instanceof SQLitePlatform => 'INSERT OR IGNORE '.$into,
            default => 'INSERT '.$into,
        };
    }

    /**
     * A moment written by `datetime_utc_immutable`, read back in UTC.
     */
    public static function date(mixed $value): DateTimeImmutable
    {
        return new DateTimeImmutable(self::text($value), new DateTimeZone('UTC'));
    }

    public static function nullableDate(mixed $value): ?DateTimeImmutable
    {
        return null === $value ? null : self::date($value);
    }

    public static function reference(mixed $value): ResourceReference
    {
        return ResourceReference::fromString(self::text($value));
    }

    public static function text(mixed $value): string
    {
        if (!\is_string($value)) {
            throw new UnexpectedValueException(\sprintf('A text column holds %s.', get_debug_type($value)));
        }

        return $value;
    }

    public static function nullableText(mixed $value): ?string
    {
        return null === $value ? null : self::text($value);
    }

    public static function integer(mixed $value): int
    {
        if (\is_int($value)) {
            return $value;
        }

        if (\is_string($value) && 1 === preg_match('/^-?\d+$/D', $value)) {
            return (int) $value;
        }

        throw new UnexpectedValueException(\sprintf('An integer column holds %s.', get_debug_type($value)));
    }

    public static function nullableInteger(mixed $value): ?int
    {
        return null === $value ? null : self::integer($value);
    }
}
