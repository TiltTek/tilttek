<?php

declare(strict_types=1);

namespace Tilttek\LootTables\Tests\Unit\Draw;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer;
use Tilttek\LootTables\Draw\PendingDraw;
use Tilttek\LootTables\Exception\InvalidLoot;

#[CoversClass(PendingDraw::class)]
#[CoversClass(InvalidLoot::class)]
final class PendingDrawTest extends TestCase
{
    private const string SEED = '00112233445566778899aabbccddeeff00112233445566778899aabbccddeeff';

    public function testANewDrawHasASeedOfItsOwnAndItsCommitment(): void
    {
        $pending = PendingDraw::open(new Randomizer(new Xoshiro256StarStar(7)), 3);
        $other = PendingDraw::open(new Randomizer(new Xoshiro256StarStar(8)));

        self::assertSame(3, $pending->nonce);
        self::assertSame($pending->seed->commitment()->hex, $pending->commitment()->hex);
        self::assertNotSame($pending->seed->hex(), $other->seed->hex());
        self::assertSame(0, $other->nonce);
    }

    public function testADrawRestoredFromItsSeed(): void
    {
        $pending = PendingDraw::restore(self::SEED, 2);

        self::assertSame(self::SEED, $pending->seed->hex());
        self::assertSame(hash('sha256', (string) hex2bin(self::SEED)), $pending->commitment()->hex);
        self::assertSame(2, $pending->nonce);
        self::assertSame(0, PendingDraw::restore(self::SEED)->nonce);
    }

    public function testASeedThatDoesNotRead(): void
    {
        $this->expectException(InvalidLoot::class);
        $this->expectExceptionMessageIsOrContains('A seed is 64 lower-case hexadecimal digits.');

        PendingDraw::restore('not a seed');
    }

    public function testAClientSeedIsOneToTwoHundredFiftySixBytes(): void
    {
        self::assertSame('a', PendingDraw::clientSeed('a'));
        self::assertSame(str_repeat('a', 256), PendingDraw::clientSeed(str_repeat('a', 256)));

        $this->expectException(InvalidLoot::class);
        $this->expectExceptionMessageIsOrContains('A client seed is 1 to 256 bytes of UTF-8 text.');

        PendingDraw::clientSeed(str_repeat('a', 257));
    }

    public function testAClientSeedIsText(): void
    {
        self::assertSame('dé', PendingDraw::clientSeed('dé'));

        $this->expectException(InvalidLoot::class);
        $this->expectExceptionMessageIsOrContains('A client seed is 1 to 256 bytes of UTF-8 text.');

        PendingDraw::clientSeed("\xff");
    }

    public function testAnEmptyClientSeed(): void
    {
        $this->expectException(InvalidLoot::class);
        $this->expectExceptionMessageIsOrContains('A client seed is 1 to 256 bytes of UTF-8 text.');

        PendingDraw::clientSeed('');
    }

    public function testANegativeNonce(): void
    {
        $this->expectException(InvalidLoot::class);
        $this->expectExceptionMessageIsOrContains('The nonce of a draw is 0 or more; -1 is not.');

        PendingDraw::open(new Randomizer(new Xoshiro256StarStar(7)), -1);
    }
}
