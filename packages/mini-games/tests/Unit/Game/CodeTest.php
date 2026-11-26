<?php

declare(strict_types=1);

namespace Tilttek\MiniGames\Tests\Unit\Game;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\MiniGames\Exception\InvalidMiniGame;
use Tilttek\MiniGames\Game\Code;

#[CoversClass(Code::class)]
#[CoversClass(InvalidMiniGame::class)]
final class CodeTest extends TestCase
{
    public function testACodeOfDifferentDigits(): void
    {
        self::assertSame('0472', Code::read('0472', 4)->digits);
        self::assertSame('987', Code::read('987', 3)->digits);
        self::assertSame('13579', Code::read('13579', 5)->digits);
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function refusedGuesses(): iterable
    {
        yield 'a digit twice' => ['0470', 4];
        yield 'too short' => ['047', 4];
        yield 'too long' => ['04721', 4];
        yield 'a letter' => ['04a2', 4];
        yield 'a line break' => ["0472\n", 4];
        yield 'empty' => ['', 4];
        yield 'a sign' => ['-472', 4];
    }

    #[DataProvider('refusedGuesses')]
    public function testARefusedGuess(string $text, int $length): void
    {
        $this->expectException(InvalidMiniGame::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('A guess is %d different digits.', $length));

        Code::read($text, $length);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function refusedLengths(): iterable
    {
        yield 'two' => [2];
        yield 'six' => [6];
    }

    #[DataProvider('refusedLengths')]
    public function testARefusedLength(int $length): void
    {
        $this->expectException(InvalidMiniGame::class);
        $this->expectExceptionMessageIsOrContains('A code has 3 to 5 digits.');

        Code::read(substr('012345', 0, $length), $length);
    }

    public function testTheBullsThenTheCows(): void
    {
        $code = Code::read('0472', 4);

        self::assertSame([4, 0], $code->score(Code::read('0472', 4)));
        self::assertSame([0, 4], $code->score(Code::read('2704', 4)));
        self::assertSame([2, 1], $code->score(Code::read('0417', 4)));
        self::assertSame([0, 0], $code->score(Code::read('1356', 4)));
        self::assertSame([1, 0], $code->score(Code::read('5612', 4)));
    }
}
