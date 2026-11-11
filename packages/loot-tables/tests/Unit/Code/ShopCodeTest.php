<?php

declare(strict_types=1);

namespace Tilttek\LootTables\Tests\Unit\Code;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\LootTables\Code\ShopCode;
use Tilttek\LootTables\Draw\DrawSource;
use Tilttek\LootTables\Draw\LootResult;
use Tilttek\LootTables\Exception\InvalidCode;
use Tilttek\LootTables\Tests\Unit\Fixture\Loot;

#[CoversClass(ShopCode::class)]
#[CoversClass(InvalidCode::class)]
final class ShopCodeTest extends TestCase
{
    /**
     * @return iterable<string, array{list<int>, list<int>, string}>
     */
    public static function codes(): iterable
    {
        yield 'six d6' => [[6, 6, 6, 6, 6, 6], [3, 5, 2, 6, 1, 4], '352614'];
        yield 'a d10 shows A for 10' => [[10, 10, 10], [10, 1, 9], 'A19'];
        yield 'a d12 shows C for 12' => [[12, 12], [11, 12], 'BC'];
        yield 'a d20 goes on to K' => [[20, 20, 20, 20], [13, 17, 19, 20], 'DHJK'];
        yield 'dice of every kind' => [[4, 8, 20, 2], [4, 8, 16, 2], '48G2'];
    }

    /**
     * @param list<int> $dice
     * @param list<int> $faces
     */
    #[DataProvider('codes')]
    public function testOneCharacterPerDie(array $dice, array $faces, string $code): void
    {
        $box = Loot::box($dice);

        self::assertTrue(ShopCode::supports($box));
        self::assertSame($code, ShopCode::write(LootResult::ofFaces($box, $faces, DrawSource::Verifiable)));
        self::assertSame($faces, ShopCode::read($box, $code)->faces());
        self::assertSame($faces, ShopCode::read($box, strtolower($code))->faces());
    }

    public function testACodeReadIsPhysicalDiceUnlessSaidOtherwise(): void
    {
        $box = Loot::box([6]);

        self::assertSame(DrawSource::Physical, ShopCode::read($box, '4')->source);
        self::assertSame(DrawSource::Verifiable, ShopCode::read($box, '4', DrawSource::Verifiable)->source);
    }

    public function testTheAlphabet(): void
    {
        self::assertSame(20, \strlen(ShopCode::ALPHABET));
        self::assertSame(20, ShopCode::MAX_SIDES);
    }

    public function testABoxWithADieOfMoreThanTwentySides(): void
    {
        $box = Loot::box([6, 21]);

        self::assertFalse(ShopCode::supports($box));

        try {
            ShopCode::write(LootResult::ofFaces($box, [1, 1], DrawSource::Verifiable));
            self::fail('A code was written for a d21.');
        } catch (InvalidCode $invalidCode) {
            self::assertSame('The box box rolls a die of more than 20 sides: it has no code.', $invalidCode->getMessage());
        }

        $this->expectException(InvalidCode::class);
        $this->expectExceptionMessageIsOrContains('The box box rolls a die of more than 20 sides: it has no code.');

        ShopCode::read($box, '11');
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function refused(): iterable
    {
        yield 'too short' => ['35261', 'A code of the box box has 6 characters, one per die.'];
        yield 'too long' => ['3526141', 'A code of the box box has 6 characters, one per die.'];
        yield 'a face the d6 does not have' => ['352617', 'Character 6 of the code is not a face of a d6: 123456.'];
        yield 'a zero' => ['052614', 'Character 1 of the code is not a face of a d6: 123456.'];
        yield 'a letter past K' => ['35Z614', 'Character 3 of the code is not a face of a d6: 123456.'];
        yield 'a space' => ['35 614', 'Character 3 of the code is not a face of a d6: 123456.'];
        yield 'a character of two bytes' => ['3526é', 'Character 5 of the code is not a face of a d6: 123456.'];
    }

    #[DataProvider('refused')]
    public function testACodeThatDoesNotRead(string $code, string $message): void
    {
        $this->expectException(InvalidCode::class);
        $this->expectExceptionMessageIsOrContains($message);

        ShopCode::read(Loot::box([6, 6, 6, 6, 6, 6]), $code);
    }
}
