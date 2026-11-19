<?php

declare(strict_types=1);

namespace Tilttek\Raffle\Tests\Unit;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\Raffle\Encoding;
use Tilttek\Raffle\Exception\InvalidRaffle;
use Tilttek\Raffle\Instant;
use Tilttek\Raffle\SystemClock;

#[CoversClass(Encoding::class)]
#[CoversClass(Instant::class)]
#[CoversClass(SystemClock::class)]
#[CoversClass(InvalidRaffle::class)]
final class EncodingTest extends TestCase
{
    public function testTheJsonIsCanonical(): void
    {
        self::assertSame('["a/b","é",1]', Encoding::json(['a/b', 'é', 1]));
        self::assertSame(hash('sha256', '["a/b","é",1]'), Encoding::hash(['a/b', 'é', 1]));
        self::assertSame('tilttek-raffle/1', Encoding::VERSION);
    }

    public function testAnIdentifierAndAnEntrantAreChecked(): void
    {
        self::assertSame('door-prize:2026.11_A', Encoding::id('door-prize:2026.11_A'));
        self::assertSame(str_repeat('a', 128), Encoding::id(str_repeat('a', 128)));
        self::assertSame('member:42~b', Encoding::entrant('member:42~b'));
        self::assertSame(str_repeat('a', 128), Encoding::entrant(str_repeat('a', 128)));
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function badIdentifiers(): iterable
    {
        yield 'empty' => [''];
        yield 'too long' => [str_repeat('a', 129)];
        yield 'a space' => ['door prize'];
        yield 'a tilde' => ['door~prize'];
        yield 'a new line at the end' => ["door\n"];
    }

    #[DataProvider('badIdentifiers')]
    public function testABadIdentifierIsRefused(string $id): void
    {
        $this->expectException(InvalidRaffle::class);
        $this->expectExceptionMessageIsOrContains('A raffle identifier is 1 to 128 letters, digits, dots, colons, underscores or hyphens.');

        Encoding::id($id);
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function badEntrants(): iterable
    {
        yield 'empty' => [''];
        yield 'too long' => [str_repeat('a', 129)];
        yield 'an address' => ['ann@example.org'];
        yield 'a name' => ['Ann Smith'];
        yield 'a new line at the end' => ["ann\n"];
    }

    #[DataProvider('badEntrants')]
    public function testABadEntrantIsRefused(string $entrant): void
    {
        $this->expectException(InvalidRaffle::class);
        $this->expectExceptionMessageIsOrContains('An entrant is an opaque reference of 1 to 128 letters, digits, dots, colons, underscores, tildes or hyphens.');

        Encoding::entrant($entrant);
    }

    public function testHexadecimalIsCheckedForItsLength(): void
    {
        self::assertSame(str_repeat('0a', 32), Encoding::hex(str_repeat('0a', 32), 'seed'));
        self::assertSame('ab', Encoding::hex('ab', 'byte', 2));

        foreach ([str_repeat('a', 63), str_repeat('a', 65), str_repeat('A', 64), str_repeat('g', 64), str_repeat('a', 63)."\n"] as $hex) {
            try {
                Encoding::hex($hex, 'seed');
                self::fail($hex.' was taken.');
            } catch (InvalidRaffle $invalid) {
                self::assertSame('The seed is 64 lower-case hexadecimal digits.', $invalid->getMessage());
            }
        }
    }

    public function testATimeIsWrittenInUtcToTheMicrosecond(): void
    {
        $at = new DateTimeImmutable('2026-11-12 15:00:00.25', new DateTimeZone('America/Toronto'));

        self::assertSame('2026-11-12T20:00:00.250000Z', Instant::write($at));
        self::assertEquals($at, Instant::read('2026-11-12T20:00:00.250000Z'));
        self::assertSame('UTC', Instant::read('2026-11-12T20:00:00.250000Z')->getTimezone()->getName());
        self::assertSame('UTC', Instant::utc($at)->getTimezone()->getName());
        self::assertEquals($at, Instant::utc($at));
    }

    public function testATimeWrittenOtherwiseIsRefused(): void
    {
        foreach (['2026-11-12T20:00:00Z', '2026-11-12 20:00:00.000000Z', '2026-13-12T20:00:00.000000Z', 'x2026-11-12T20:00:00.000000Z'] as $text) {
            try {
                Instant::read($text);
                self::fail($text.' was read.');
            } catch (InvalidRaffle $invalid) {
                self::assertSame('A time is written in UTC to the microsecond, as 2026-11-12T20:15:03.250000Z.', $invalid->getMessage());
            }
        }
    }

    public function testTheSystemClockGivesNow(): void
    {
        $before = new DateTimeImmutable('-1 second');

        self::assertGreaterThanOrEqual($before, new SystemClock()->now());
        self::assertLessThanOrEqual(new DateTimeImmutable('+1 second'), new SystemClock()->now());
    }

    public function testHexadecimalIsCheckedFromItsFirstDigit(): void
    {
        $this->expectException(InvalidRaffle::class);

        Encoding::hex('g'.str_repeat('a', 63), 'seed');
    }
}
