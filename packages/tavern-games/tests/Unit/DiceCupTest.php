<?php

declare(strict_types=1);

namespace Tilttek\TavernGames\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\GameEngine\Randomness;
use Tilttek\TavernGames\DiceCup;
use Tilttek\TavernGames\Exception\InvalidTavernGame;

#[CoversClass(DiceCup::class)]
#[CoversClass(InvalidTavernGame::class)]
final class DiceCupTest extends TestCase
{
    public function testTheFacesOfTheDice(): void
    {
        $cup = new DiceCup();

        foreach ([1, 5, 10] as $count) {
            $random = new Randomness(str_repeat('ab', 32), 3);
            $faces = $cup->roll($count, $random);

            self::assertCount($count, $faces);
            self::assertTrue($random->drawn());

            foreach ($faces as $face) {
                self::assertGreaterThanOrEqual(1, $face);
                self::assertLessThanOrEqual(6, $face);
            }
        }
    }

    public function testTheSameChanceRollsTheSameDice(): void
    {
        $cup = new DiceCup();

        self::assertSame($cup->roll(5, new Randomness(str_repeat('ab', 32), 3)), $cup->roll(5, new Randomness(str_repeat('ab', 32), 3)));
        self::assertNotSame($cup->roll(10, new Randomness(str_repeat('ab', 32), 3)), $cup->roll(10, new Randomness(str_repeat('ab', 32), 4)));
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function refused(): iterable
    {
        yield 'none' => [0];
        yield 'eleven' => [11];
    }

    #[DataProvider('refused')]
    public function testARefusedCount(int $count): void
    {
        $this->expectException(InvalidTavernGame::class);
        $this->expectExceptionMessageIsOrContains('A roll throws 1 to 10 dice.');

        new DiceCup()->roll($count, new Randomness(str_repeat('ab', 32), 1));
    }
}
