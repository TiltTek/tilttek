<?php

declare(strict_types=1);

namespace Tilttek\LootTablesBundle\Tests\Unit\Application;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Random\Engine\Secure;
use ReflectionClass;
use ReflectionNamedType;
use Tilttek\Dice\Fairness\Seed;
use Tilttek\LootTables\Draw\PendingDraw;
use Tilttek\LootTablesBundle\Application\SecureSeeds;

#[CoversClass(SecureSeeds::class)]
final class SecureSeedsTest extends TestCase
{
    public function testTheSeedsComeFromTheCryptographicGeneratorOnly(): void
    {
        $constructor = new ReflectionClass(SecureSeeds::class)->getConstructor();
        self::assertNotNull($constructor);
        $parameters = $constructor->getParameters();
        $type = $parameters[0]->getType();

        self::assertCount(1, $parameters);
        self::assertInstanceOf(ReflectionNamedType::class, $type);
        self::assertSame(Secure::class, $type->getName());
        self::assertFalse($type->allowsNull());
        self::assertTrue(new ReflectionClass(Secure::class)->isFinal());
    }

    public function testEachSeedIsThirtyTwoNewBytesCommittedByTheirSha256(): void
    {
        $seeds = new SecureSeeds();
        $hexes = [];

        for ($i = 0; $i < 64; ++$i) {
            $pending = $seeds->open();
            self::assertInstanceOf(PendingDraw::class, $pending);
            self::assertSame(0, $pending->nonce);
            $hex = $pending->seed->hex();
            self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/D', $hex);
            self::assertSame(Seed::fromHex($hex)->commitment(0)->hex, $pending->commitment()->hex);
            $hexes[] = $hex;
        }

        self::assertCount(64, array_unique($hexes));
        self::assertSame(32, SecureSeeds::BYTES);
        self::assertSame(64, \strlen(new SecureSeeds(new Secure())->open()->seed->hex()));
    }
}
