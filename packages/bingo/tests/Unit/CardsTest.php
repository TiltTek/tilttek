<?php

declare(strict_types=1);

namespace Tilttek\Bingo\Tests\Unit;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Tilttek\Bingo\BingoGame;
use Tilttek\Bingo\Card;
use Tilttek\Bingo\CardDealer;
use Tilttek\Bingo\Exception\BingoRefused;
use Tilttek\Bingo\Exception\InvalidBingo;
use Tilttek\Bingo\Grid;
use Tilttek\Bingo\Square;
use Tilttek\Bingo\SquarePool;
use Tilttek\Bingo\Tests\Unit\Fixture\Games;
use Tilttek\Bingo\TickMode;
use Tilttek\Bingo\WinPatterns;

#[CoversClass(CardDealer::class)]
#[CoversClass(Card::class)]
#[CoversClass(BingoGame::class)]
#[CoversClass(BingoRefused::class)]
final class CardsTest extends TestCase
{
    public function testACardIsDrawnFromTheSecretTheGameAndTheSerialAlone(): void
    {
        $game = $this->game();
        $dealer = new CardDealer(Games::SECRET);

        self::assertSame(['s11', 's5', 's1', 's12', null, 's9', 's3', 's4', 's6'], $dealer->layout($game, 1));
        self::assertSame(['s5', 's2', 's8', 's4', null, 's3', 's10', 's1', 's9'], $dealer->layout($game, 2));
        self::assertSame($dealer->layout($game, 1), new CardDealer(Games::SECRET)->layout($this->game(), 1));
        self::assertNotSame($dealer->layout($game, 1), new CardDealer(str_repeat('k', 32))->layout($game, 1));
        self::assertNotSame($dealer->layout($game, 1), $dealer->layout(new BingoGame('con-2027', Games::pool(12), new Grid(3), WinPatterns::line(new Grid(3))), 1));
        self::assertSame('823cde83a102a565d3dc5018cb007f7a9c7f40a4491bfdb91566f7af9718d414', CardDealer::fingerprint($dealer->layout($game, 1)));
        self::assertNotSame(CardDealer::fingerprint(['a', null]), CardDealer::fingerprint([null, 'a']));
    }

    public function testACardWithoutAFreeCentreFillsEveryCell(): void
    {
        $game = new BingoGame('g', Games::pool(9), new Grid(3, false), WinPatterns::line(new Grid(3, false)));
        $layout = new CardDealer(Games::SECRET)->layout($game, 1);

        self::assertCount(9, $layout);
        self::assertNotContains(null, $layout);
        $sorted = $layout;
        sort($sorted);
        self::assertSame(['s1', 's2', 's3', 's4', 's5', 's6', 's7', 's8', 's9'], $sorted);
    }

    public function testTheSecretIsLongEnough(): void
    {
        self::assertInstanceOf(CardDealer::class, new CardDealer(str_repeat('k', 32)));

        $this->expectException(InvalidBingo::class);
        $this->expectExceptionMessageIsOrContains('The secret that draws the cards is at least 32 bytes.');
        new CardDealer(str_repeat('k', 31));
    }

    public function testACardIsTickedByGesturesAndUntickedWhenAllAreCancelled(): void
    {
        $card = new Card('g', 1, 'member:1', ['a', 'b', null, 'a']);

        self::assertSame([0, 3], $card->cellsOf('a'));
        self::assertSame([], $card->cellsOf('z'));
        self::assertSame(1, $card->version());
        self::assertSame([0, 3], $card->tick('a', 'k1'));
        self::assertSame(2, $card->version());
        self::assertSame([], $card->tick('a', 'k1'));
        self::assertSame(2, $card->version());
        self::assertSame([], $card->tick('a', 'k2'));
        self::assertSame(3, $card->version());
        self::assertSame([], $card->tick('z', 'k3'));
        self::assertSame(3, $card->version());
        self::assertSame([0 => ['k1', 'k2'], 3 => ['k1', 'k2']], $card->ticks());

        self::assertSame([], $card->untick('k1'));
        self::assertSame(4, $card->version());
        self::assertSame([0 => ['k2'], 3 => ['k2']], $card->ticks());
        self::assertSame([], $card->untick('k9'));
        self::assertSame(4, $card->version());
        self::assertSame([0, 3], $card->untick('k2'));
        self::assertSame(5, $card->version());
        self::assertSame([], $card->ticks());
    }

    public function testACellRemembersAFewGestures(): void
    {
        $card = new Card('g', 1, 'member:1', ['a', 'b']);

        for ($key = 1; $key <= Card::MOST_KEYS + 2; ++$key) {
            $card->tick('a', 'k'.$key);
        }

        self::assertCount(Card::MOST_KEYS, $card->ticks()[0]);
        self::assertSame('k16', $card->ticks()[0][15]);
    }

    public function testACardThatWonStaysWon(): void
    {
        $card = new Card('g', 1, 'member:1', ['a', 'b']);
        $card->tick('a', 'k1');

        $at = new DateTimeImmutable('2026-11-12 20:00:00 UTC');

        self::assertFalse($card->hasWon());
        self::assertNull($card->pattern());
        self::assertNull($card->wonAt());
        $card->win('row', $at);
        self::assertTrue($card->hasWon());
        self::assertSame('row', $card->pattern());
        self::assertSame($at, $card->wonAt());
        self::assertSame(3, $card->version());

        $card->win('column', $at->modify('+1 hour'));
        self::assertSame('row', $card->pattern());
        self::assertSame($at, $card->wonAt());
        self::assertSame(3, $card->version());
        self::assertSame([], $card->untick('k1'));
        self::assertSame([0 => ['k1']], $card->ticks());
    }

    public function testACardHasAPlayerAndASerialFromOne(): void
    {
        $card = new Card('g', 1, 'member:1', ['a']);
        self::assertSame('g', $card->game);
        self::assertSame(1, $card->serial);
        self::assertSame('member:1', $card->player);
        self::assertSame(['a'], $card->layout);

        try {
            new Card('g', 0, 'member:1', ['a']);
            self::fail('A serial of 0 was accepted.');
        } catch (InvalidBingo $invalidBingo) {
            self::assertSame('The serial of a card starts at 1; 0 is not one.', $invalidBingo->getMessage());
        }

        $this->expectException(InvalidBingo::class);
        new Card('g', 1, 'Jane Doe', ['a']);
    }

    public function testAGameCountsItsCardsKeepsItsCallsAndItsWinners(): void
    {
        $game = new BingoGame('g', Games::pool(12), new Grid(3), WinPatterns::line(new Grid(3)), TickMode::Called);

        self::assertSame('g', $game->id);
        self::assertSame(TickMode::Called, $game->mode);
        self::assertTrue($game->isOpen());
        self::assertSame(0, $game->dealt());
        self::assertSame(1, $game->nextSerial());
        self::assertSame(2, $game->nextSerial());
        self::assertSame(2, $game->dealt());
        self::assertSame(3, $game->version());

        self::assertTrue($game->call('s3'));
        self::assertFalse($game->call('s3'));
        self::assertTrue($game->call('s1'));
        self::assertSame(['s3', 's1'], $game->called());
        self::assertTrue($game->isCalled('s1'));
        self::assertFalse($game->isCalled('s2'));
        self::assertSame(5, $game->version());

        $game->won(2);
        $game->won(2);
        $game->won(1);
        self::assertSame([2, 1], $game->winners());
        self::assertSame(7, $game->version());

        $game->close();
        self::assertFalse($game->isOpen());
        self::assertSame(8, $game->version());

        foreach ([$game->nextSerial(...), static fn (): bool => $game->call('s2'), $game->close(...)] as $move) {
            try {
                $move();
                self::fail('A closed game moved.');
            } catch (BingoRefused $exception) {
                self::assertSame('The bingo game g is closed.', $exception->getMessage());
            }
        }
    }

    public function testAGameTickedByActivityIsNeverCalledAndItsSquaresAreItsPools(): void
    {
        $game = $this->game();

        try {
            $game->call('s1');
            self::fail('A square was called in a game ticked by activity.');
        } catch (BingoRefused $bingoRefused) {
            self::assertSame('The squares of the bingo game con-2026 are ticked by activity, never called.', $bingoRefused->getMessage());
        }

        $called = new BingoGame('g', Games::pool(12), new Grid(3), WinPatterns::line(new Grid(3)), TickMode::Called);
        $this->expectException(InvalidBingo::class);
        $this->expectExceptionMessageIsOrContains('The pool has no square s99.');
        $called->call('s99');
    }

    public function testAPoolHoldsAtLeastTheSquaresOfACard(): void
    {
        self::assertSame(8, new BingoGame('g', Games::pool(8), new Grid(3), WinPatterns::line(new Grid(3)))->pool->count());

        try {
            new BingoGame('g', Games::pool(7), new Grid(3), WinPatterns::line(new Grid(3)));
            self::fail('A pool too small was accepted.');
        } catch (InvalidBingo $invalidBingo) {
            self::assertSame('A card of the game g holds 8 squares; its pool holds 7.', $invalidBingo->getMessage());
        }

        $this->expectException(InvalidBingo::class);
        new BingoGame('a game', new SquarePool([new Square('a', 'A')]), new Grid(3), WinPatterns::line(new Grid(3)));
    }

    public function testAGameDealsABoundedNumberOfCards(): void
    {
        $game = $this->game();
        $dealt = new ReflectionProperty(BingoGame::class, 'dealt');
        $dealt->setValue($game, BingoGame::MOST_CARDS - 1);

        self::assertSame(BingoGame::MOST_CARDS, $game->nextSerial());

        $this->expectException(BingoRefused::class);
        $this->expectExceptionMessageIsOrContains('The bingo game con-2026 dealt 100000 cards, the most it deals.');
        $game->nextSerial();
    }

    private function game(): BingoGame
    {
        return new BingoGame('con-2026', Games::pool(12), new Grid(3), WinPatterns::line(new Grid(3)));
    }

    public function testACancelledGestureUnticksOnlyItsOwnCells(): void
    {
        $card = new Card('g', 1, 'member:1', ['a', 'b', null, 'c']);
        $card->tick('a', 'k1');
        $card->tick('b', 'k2');

        self::assertSame([1], $card->untick('k2'));
        self::assertSame([0 => ['k1']], $card->ticks());
    }

    public function testAGameHasAnIdentifier(): void
    {
        $this->expectException(InvalidBingo::class);
        $this->expectExceptionMessageIsOrContains('The identifier of a game is 1 to 128 letters');
        new BingoGame('the game', Games::pool(12), new Grid(3), WinPatterns::line(new Grid(3)));
    }

    public function testTheSquaresCalledAreAListInTheirOrder(): void
    {
        $game = new BingoGame('g', Games::pool(12), new Grid(3), WinPatterns::line(new Grid(3)), TickMode::Called);
        $game->call('s7');
        $game->call('s2');

        self::assertSame(['s7', 's2'], $game->called());
    }

    public function testOneGestureTicksSeveralSquaresInOneVersion(): void
    {
        $card = new Card('g', 1, 'member:1', ['a', 'b', null, 'c']);
        $card->tick('a', 'k0');
        self::assertSame(2, $card->version());

        self::assertSame(['b', 'c'], $card->tickAll(['z', 'b', 'c'], 'k1'));
        self::assertSame(3, $card->version());
        self::assertSame([], $card->tickAll(['z', 'b'], 'k1'));
        self::assertSame(3, $card->version());
        self::assertSame([], $card->tickAll(['a'], 'k1'));
        self::assertSame(4, $card->version());
        self::assertSame([0 => ['k0', 'k1'], 1 => ['k1'], 3 => ['k1']], $card->ticks());
        self::assertSame([], $card->tickAll([], 'k2'));
        self::assertSame(4, $card->version());
    }
}
