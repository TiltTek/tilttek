<?php

declare(strict_types=1);

namespace Tilttek\Bingo\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\Bingo\Bingo;
use Tilttek\Bingo\BingoGame;
use Tilttek\Bingo\Card;
use Tilttek\Bingo\CardDealer;
use Tilttek\Bingo\Event\BingoWon;
use Tilttek\Bingo\Exception\BingoConflict;
use Tilttek\Bingo\Exception\BingoRefused;
use Tilttek\Bingo\Exception\InvalidBingo;
use Tilttek\Bingo\Grid;
use Tilttek\Bingo\SerialLabel;
use Tilttek\Bingo\Store\InMemoryBingoStore;
use Tilttek\Bingo\Tests\Unit\Fixture\FrozenClock;
use Tilttek\Bingo\Tests\Unit\Fixture\Games;
use Tilttek\Bingo\Tests\Unit\Fixture\RecordingDispatcher;
use Tilttek\Bingo\Tests\Unit\Fixture\ScriptedStore;
use Tilttek\Bingo\TickMode;
use Tilttek\Bingo\View\CellView;
use Tilttek\Bingo\View\PrintedCardView;
use Tilttek\Bingo\WinPatterns;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Loader\ChainLoader;
use Twig\Loader\FilesystemLoader;

#[CoversClass(Bingo::class)]
#[CoversClass(BingoGame::class)]
#[CoversClass(Card::class)]
#[CoversClass(CardDealer::class)]
#[CoversClass(SerialLabel::class)]
#[CoversClass(PrintedCardView::class)]
#[CoversClass(InMemoryBingoStore::class)]
final class PrintingTest extends TestCase
{
    private RecordingDispatcher $events;

    private ScriptedStore $store;

    private Bingo $bingo;

    protected function setUp(): void
    {
        $this->events = new RecordingDispatcher();
        $this->store = new ScriptedStore();
        $this->bingo = new Bingo($this->store, new CardDealer(Games::SECRET), $this->events, new FrozenClock());
        $grid = new Grid(3);
        $this->bingo->create('night', Games::pool(12), $grid, WinPatterns::line($grid), TickMode::Called);
    }

    public function testABatchIsPrintedWithReservedSerialsAndNoPlayer(): void
    {
        $this->bingo->deal('night', 'member:1');
        $saves = $this->store->gameSaves;

        $cards = $this->bingo->print('night', 3);

        self::assertSame([2, 3, 4], array_map(static fn (Card $card): int => $card->serial, $cards));
        self::assertSame($saves + 1, $this->store->gameSaves);
        self::assertSame(4, $this->bingo->game('night')->dealt());

        foreach ($cards as $card) {
            self::assertNull($card->player);
            self::assertTrue($card->isPrinted());
            self::assertSame(new CardDealer(Games::SECRET)->layout($this->bingo->game('night'), $card->serial), $card->layout);
            self::assertEquals($card, $this->bingo->card('night', $card->serial));
        }

        self::assertFalse($this->bingo->card('night', 1)->isPrinted());
        self::assertSame(5, $this->bingo->deal('night', 'member:2')->serial);
    }

    public function testASerialWhoseLayoutIsTakenIsPassedOverForANewOne(): void
    {
        $this->store->takenFingerprints = 2;

        $cards = $this->bingo->print('night', 2);

        self::assertSame([3, 4], array_map(static fn (Card $card): int => $card->serial, $cards));
        self::assertSame(4, $this->bingo->game('night')->dealt());
        self::assertNull($this->store->card('night', 1));
    }

    public function testABatchGivesUpAfterSoManyLayoutsTaken(): void
    {
        $this->store->takenFingerprints = Bingo::DEAL_ATTEMPTS;
        self::assertCount(1, $this->bingo->print('night', 1));

        $this->store->takenFingerprints = Bingo::DEAL_ATTEMPTS + 1;

        $this->expectException(BingoRefused::class);
        $this->expectExceptionMessageIsOrContains('No card unlike the others was found for a batch of the bingo game night.');
        $this->bingo->print('night', 1);
    }

    public function testTheSizeOfABatchAndTheModeOfTheGameAreChecked(): void
    {
        $big = new Bingo(new InMemoryBingoStore(), new CardDealer(Games::SECRET), $this->events, new FrozenClock());
        $big->create('big', Games::pool(500), new Grid(5), WinPatterns::line(new Grid(5)), TickMode::Called);
        self::assertCount(Bingo::MOST_PRINTED, $big->print('big', Bingo::MOST_PRINTED));
        $grid = new Grid(3);
        $this->bingo->create('con', Games::pool(12), $grid, WinPatterns::line($grid));

        foreach ([
            'A batch prints 1 to 1000 cards; 0 asked.' => fn (): array => $this->bingo->print('night', 0),
            'A batch prints 1 to 1000 cards; 1001 asked.' => fn (): array => $this->bingo->print('night', Bingo::MOST_PRINTED + 1),
            'The cards of the bingo game con are ticked by activity: each belongs to a member, none is printed.' => fn (): array => $this->bingo->print('con', 1),
            'No bingo game has the identifier nope.' => fn (): array => $this->bingo->print('nope', 1),
        ] as $message => $print) {
            try {
                $print();
                self::fail('A batch was printed: '.$message);
            } catch (InvalidBingo|BingoRefused $exception) {
                self::assertSame($message, $exception->getMessage());
            }
        }

        self::assertSame(0, $this->bingo->game('con')->dealt());
    }

    public function testAGameReservesSerialsWithinWhatItDeals(): void
    {
        $game = new BingoGame('g', Games::pool(12), new Grid(3), WinPatterns::line(new Grid(3)), TickMode::Called);

        self::assertSame(1, $game->nextSerials(2));
        self::assertSame(3, $game->nextSerials(BingoGame::MOST_CARDS - 2));
        self::assertSame(BingoGame::MOST_CARDS, $game->dealt());
        self::assertSame(3, $game->version());

        foreach ([
            'A batch of serials holds at least one; 0 asked.' => static fn (): int => new BingoGame('h', Games::pool(12), new Grid(3), WinPatterns::line(new Grid(3)))->nextSerials(0),
            'The bingo game g dealt 100000 cards; 1 more would pass the 100000 it deals.' => static fn (): int => $game->nextSerials(1),
        ] as $message => $reserve) {
            try {
                $reserve();
                self::fail('Serials were reserved: '.$message);
            } catch (InvalidBingo|BingoRefused $exception) {
                self::assertSame($message, $exception->getMessage());
            }
        }

        $closed = new BingoGame('c', Games::pool(12), new Grid(3), WinPatterns::line(new Grid(3)));
        $closed->close();
        $this->expectException(BingoRefused::class);
        $this->expectExceptionMessageIsOrContains('The bingo game c is closed.');
        $closed->nextSerials(1);
    }

    public function testAPrintedCardIsCheckedByItsLabelAndWinsOnTheSquaresCalled(): void
    {
        [$card] = $this->bingo->print('night', 1);
        $label = $this->bingo->label('night', $card->serial);

        self::assertSame(1, $label->serial);
        self::assertSame('1-HW3H-MXSE', $label->toString());
        self::assertEquals(new CardDealer(Games::SECRET)->label($this->bingo->game('night'), 1), $label);
        self::assertNotEquals($label, $this->bingo->label('night', 2));
        self::assertNotEquals($label, new CardDealer(str_repeat('k', 32))->label($this->bingo->game('night'), 1));
        self::assertSame('1-'.substr($label->code, 0, 4).'-'.substr($label->code, 4), $label->toString());

        self::assertFalse($this->bingo->verifyLabel('night', $label->toString())->isWon());

        foreach ([$card->layout[0], $card->layout[1], $card->layout[2]] as $square) {
            $this->bingo->call('night', (string) $square);
        }

        $typed = strtolower(str_replace('-', ' ', $label->toString()));
        $claim = $this->bingo->verifyLabel('night', $typed);

        self::assertTrue($claim->isWon());
        self::assertSame(1, $claim->serial);
        self::assertContainsEquals(new BingoWon('night', 1, null, 'row'), $this->events->events);
    }

    public function testALabelNotDrawnBySecretIsRefused(): void
    {
        $this->bingo->print('night', 1);
        $label = $this->bingo->label('night', 1);
        $forged = SerialLabel::of(1, '0' === $label->code[0] ? '1'.substr($label->code, 1) : '0'.substr($label->code, 1));

        $this->expectException(BingoRefused::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('The label %s is not the label of a card of the bingo game night.', $forged->toString()));
        $this->bingo->verifyLabel('night', $forged->toString());
    }

    public function testAPrintedCardIsNeverAPlayersCard(): void
    {
        $this->bingo->print('night', 1);

        self::assertSame([], $this->store->openCardsOf('member:1', 10));
        self::assertNull($this->store->cardOf('night', 'member:1'));

        $this->expectException(BingoRefused::class);
        $this->expectExceptionMessageIsOrContains('The card 1 of the bingo game night is not the card of this player.');
        $this->bingo->claim('night', 1, 'member:1');
    }

    public function testTwoPrintedCardsTakeTwoSerialsOfTheStore(): void
    {
        $store = new InMemoryBingoStore();
        $game = new BingoGame('g', Games::pool(12), new Grid(3), WinPatterns::line(new Grid(3)), TickMode::Called);
        $store->addGame($game);

        self::assertTrue($store->addCard(new Card('g', 1, null, ['a']), 'f1'));
        self::assertTrue($store->addCard(new Card('g', 2, null, ['b']), 'f2'));
        self::assertTrue($store->addCard(new Card('g', 3, 'member:1', ['c']), 'f3'));
        self::assertSame(3, $store->cardOf('g', 'member:1')?->serial);

        $this->expectException(BingoConflict::class);
        $store->addCard(new Card('g', 2, null, ['d']), 'f4');
    }

    /**
     * @return iterable<string, array{string, int, string}>
     */
    public static function labels(): iterable
    {
        yield 'as printed' => ['42-7KQ3-M9XA', 42, '7KQ3M9XA'];
        yield 'typed in lower case with spaces' => [' 42 7kq3 m9xa ', 42, '7KQ3M9XA'];
        yield 'without the middle hyphen' => ['42-7KQ3M9XA', 42, '7KQ3M9XA'];
        yield 'with leading zeros' => ['0000042-7KQ3-M9XA', 42, '7KQ3M9XA'];
        yield 'with O, I and L for 0 and 1' => ['7-OIL0-ilo1', 7, '01101101'];
        yield 'the last serial' => ['100000-0000-0000', 100000, '00000000'];
        yield 'several separators' => ['1 - 0000 0000', 1, '00000000'];
    }

    #[DataProvider('labels')]
    public function testALabelIsReadAsAnOrganiserTypesIt(string $input, int $serial, string $code): void
    {
        $label = SerialLabel::parse($input);

        self::assertSame($serial, $label->serial);
        self::assertSame($code, $label->code);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function notLabels(): iterable
    {
        $shape = 'A label is the serial of a card and its check code, as in 42-7KQ3-M9XA.';

        yield 'no separator' => ['427KQ3M9XA', $shape];
        yield 'a code too short' => ['42-7KQ3-M9X', $shape];
        yield 'a code too long' => ['42-7KQ3-M9XAB', $shape];
        yield 'eight digits of serial' => ['10000000-0000-0000', $shape];
        yield 'another separator' => ['42.7KQ3.M9XA', $shape];
        yield 'a line after the label' => ["42-7KQ3-M9XA\nX", $shape];
        yield 'no serial' => ['-7KQ3-M9XA', $shape];
        yield 'a U' => ['42-7KQ3-M9XU', 'The check code of a card is 8 characters of base 32: digits and capital letters without I, L, O and U.'];
        yield 'serial 0' => ['0-7KQ3-M9XA', 'The serial of a card is 1 to 100000; 0 is not one.'];
        yield 'a serial past the most' => ['100001-7KQ3-M9XA', 'The serial of a card is 1 to 100000; 100001 is not one.'];
        yield 'too long' => [str_repeat(' ', 21).'42-7KQ3-M9XA', 'A label is read from at most 32 bytes.'];
    }

    #[DataProvider('notLabels')]
    public function testWhatIsNotALabelIsRefused(string $input, string $message): void
    {
        $this->expectException(InvalidBingo::class);
        $this->expectExceptionMessageIsOrContains($message);
        SerialLabel::parse($input);
    }

    public function testThirtyTwoBytesAreRead(): void
    {
        self::assertSame(42, SerialLabel::parse(str_repeat(' ', 20).'42-7KQ3-M9XA')->serial);
    }

    public function testACodeIsFortyBitsInBase32(): void
    {
        self::assertSame('00000000', SerialLabel::encode("\0\0\0\0\0"));
        self::assertSame('00000001', SerialLabel::encode("\0\0\0\0\x01"));
        self::assertSame('0000000Z', SerialLabel::encode("\0\0\0\0\x1F"));
        self::assertSame('00000010', SerialLabel::encode("\0\0\0\0\x20"));
        self::assertSame('Z0000000', SerialLabel::encode("\xF8\0\0\0\0"));
        self::assertSame('ZZZZZZZZ', SerialLabel::encode("\xFF\xFF\xFF\xFF\xFF"));
        self::assertSame('ABCDEFGH', SerialLabel::of(1, 'ABCDEFGH')->code);

        foreach (["\0\0\0\0", "\0\0\0\0\0\0"] as $bytes) {
            try {
                SerialLabel::encode($bytes);
                self::fail('A code was drawn from another length.');
            } catch (InvalidBingo $invalidBingo) {
                self::assertSame('A check code is drawn from 5 bytes.', $invalidBingo->getMessage());
            }
        }
    }

    public function testABatchIsShownCardByCardWithItsLabel(): void
    {
        $cards = $this->bingo->print('night', 2);
        $game = $this->bingo->game('night');
        $views = PrintedCardView::ofBatch($this->bingo, $game, $cards);

        self::assertCount(2, $views);
        self::assertSame('night', $views[1]->game);
        self::assertSame(2, $views[1]->serial);
        self::assertSame($this->bingo->label('night', 2)->toString(), $views[1]->label);
        self::assertCount(3, $views[0]->rows);
        self::assertCount(3, $views[0]->rows[2]);
        self::assertEquals(new CellView(null, null, false), $views[0]->rows[1][1]);
        self::assertEquals(new CellView((string) $cards[0]->layout[0], 'Square '.substr((string) $cards[0]->layout[0], 1), false), $views[0]->rows[0][0]);

        $html = $this->twig()->render('sheet.html.twig', ['cards' => $views, 'labels' => ['check' => 'Code']]);

        self::assertSame(2, substr_count($html, '<section class="isBingoPrintedCard"'));
        self::assertStringContainsString('data-game="night" data-serial="2"', $html);
        self::assertStringContainsString('<caption>Bingo card <data value="1">1</data></caption>', $html);
        self::assertStringContainsString('<p class="isBingoLabel">Code <code>'.$views[0]->label.'</code></p>', $html);
        self::assertStringContainsString('<td class="isBingoCell isFree">Free</td>', $html);
        self::assertStringContainsString('<td class="isBingoCell">Square '.substr((string) $cards[0]->layout[0], 1).'</td>', $html);
        self::assertSame(2, substr_count($html, '<p class="rules">Shout bingo</p>'));
        self::assertStringNotContainsString('isTicked', $html);
    }

    private function twig(): Environment
    {
        $loader = new FilesystemLoader();
        $loader->addPath(\dirname(__DIR__, 2).'/templates', 'TilttekBingo');
        $loader = new ChainLoader([$loader, new ArrayLoader([
            'sheet.html.twig' => "{% extends '@TilttekBingo/print.html.twig' %}{% block footer %}<p class=\"rules\">Shout bingo</p>{% endblock %}",
        ])]);

        return new Environment($loader, ['strict_variables' => true, 'autoescape' => 'html']);
    }

    public function testACodeIsExactlyEightCharactersOfTheAlphabet(): void
    {
        foreach (['XABCDEFGH', 'ABCDEFGHX', "ABCDEFGH\n", 'ABCDEFG', 'ABCDEFGU'] as $code) {
            try {
                SerialLabel::of(1, $code);
                self::fail('A code was accepted: '.$code);
            } catch (InvalidBingo $invalidBingo) {
                self::assertSame('The check code of a card is 8 characters of base 32: digits and capital letters without I, L, O and U.', $invalidBingo->getMessage());
            }
        }
    }

    public function testAPrintedCardIsShownAlone(): void
    {
        [$card] = $this->bingo->print('night', 1);

        self::assertSame($this->bingo->label('night', 1)->toString(), PrintedCardView::of($this->bingo, $this->bingo->game('night'), $card)->label);
    }
}
