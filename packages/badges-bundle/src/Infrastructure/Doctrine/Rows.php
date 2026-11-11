<?php

declare(strict_types=1);

namespace Tilttek\BadgesBundle\Infrastructure\Doctrine;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use UnexpectedValueException;

/**
 * What the stores share: the insertion that writes nothing when its row is already there, and the reading of the
 * values a row holds. Dates are written in UTC, to the second.
 */
final class Rows
{
    private function __construct()
    {
    }

    /**
     * The insertion that writes nothing when a row with the same unique columns is there, without aborting the
     * surrounding transaction: `ON CONFLICT DO NOTHING` on PostgreSQL, `INSERT IGNORE` on MySQL and MariaDB,
     * `INSERT OR IGNORE` on SQLite; elsewhere a plain insertion, whose unique violation reaches the caller.
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

    public static function utc(DateTimeImmutable $date): DateTimeImmutable
    {
        return $date->setTimezone(new DateTimeZone('UTC'));
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
}
