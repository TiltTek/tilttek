<?php

declare(strict_types=1);

namespace Tilttek\ExchangeBundle\Tests\Unit\Infrastructure;

use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Platforms\SQLServerPlatform;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\ExchangeBundle\Infrastructure\Doctrine\Rows;
use UnexpectedValueException;

#[CoversClass(Rows::class)]
final class RowsTest extends TestCase
{
    public function testAnInsertionThatWritesNothingWhenTheKeyIsThere(): void
    {
        self::assertSame('INSERT INTO t (a) VALUES (?) ON CONFLICT (a) DO NOTHING', Rows::ignoring(new PostgreSQLPlatform(), 'INTO t (a) VALUES (?)', 'a'));
        self::assertSame('INSERT IGNORE INTO t (a) VALUES (?)', Rows::ignoring(new MySQLPlatform(), 'INTO t (a) VALUES (?)', 'a'));
        self::assertSame('INSERT IGNORE INTO t (a) VALUES (?)', Rows::ignoring(new MariaDBPlatform(), 'INTO t (a) VALUES (?)', 'a'));
        self::assertSame('INSERT OR IGNORE INTO t (a) VALUES (?)', Rows::ignoring(new SQLitePlatform(), 'INTO t (a) VALUES (?)', 'a'));
        self::assertSame('INSERT INTO t (a) VALUES (?)', Rows::ignoring(new SQLServerPlatform(), 'INTO t (a) VALUES (?)', 'a'));
    }

    public function testTheColumnsReadBack(): void
    {
        self::assertSame('2026-11-07 12:00:00 +00:00', Rows::date('2026-11-07 12:00:00')->format('Y-m-d H:i:s P'));
        self::assertNull(Rows::nullableDate(null));
        self::assertSame('2026-11-07', Rows::nullableDate('2026-11-07 12:00:00')?->format('Y-m-d'));
        self::assertSame('member:1', Rows::reference('member:1')->toString());
        self::assertNull(Rows::nullableText(null));
        self::assertSame('a', Rows::nullableText('a'));
        self::assertSame(12, Rows::integer(12));
        self::assertSame(-12, Rows::integer('-12'));
        self::assertNull(Rows::nullableInteger(null));
        self::assertSame(3, Rows::nullableInteger('3'));
    }

    public function testATextColumnHoldsText(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessageIsOrContains('A text column holds int.');

        Rows::text(12);
    }

    public function testAnIntegerColumnHoldsAnInteger(): void
    {
        foreach (['12a', 'a12', '1.5', '', "12\n"] as $value) {
            try {
                Rows::integer($value);
                self::fail('A refusal was expected.');
            } catch (UnexpectedValueException $refused) {
                self::assertSame('An integer column holds string.', $refused->getMessage());
            }
        }

        $this->expectExceptionMessageIsOrContains('An integer column holds float.');

        Rows::integer(1.0);
    }
}
