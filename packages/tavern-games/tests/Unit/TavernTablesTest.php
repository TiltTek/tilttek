<?php

declare(strict_types=1);

namespace Tilttek\TavernGames\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\GameEngine\Exception\UnknownTable;
use Tilttek\GameEngine\Move;
use Tilttek\GameEngine\Store\InMemoryLogStore;
use Tilttek\GameEngine\Tables;
use Tilttek\TavernGames\Contract\TavernGame;
use Tilttek\TavernGames\Exception\InvalidTavernGame;
use Tilttek\TavernGames\Game\Pig;
use Tilttek\TavernGames\Game\ShipCaptainCrew;
use Tilttek\TavernGames\Store\InMemoryTurnTimer;
use Tilttek\TavernGames\Store\NullTurnTimer;
use Tilttek\TavernGames\TavernGames;
use Tilttek\TavernGames\TavernTables;
use Tilttek\TavernGames\Tone;

#[CoversClass(TavernTables::class)]
#[CoversClass(InMemoryTurnTimer::class)]
#[CoversClass(NullTurnTimer::class)]
final class TavernTablesTest extends TestCase
{
    private const string SEED = 'abababababababababababababababababababababababababababababababab';

    private InMemoryTurnTimer $timer;

    private Tables $tables;

    private TavernTables $tavern;

    protected function setUp(): void
    {
        $games = new TavernGames(new Pig(), new ShipCaptainCrew());
        $this->timer = new InMemoryTurnTimer();
        $this->tables = new Tables($games->games(), new InMemoryLogStore());
        $this->tavern = new TavernTables($this->tables, $games, $this->timer);
    }

    public function testOpeningATableStartsTheTurnOfTheFirstSeat(): void
    {
        $state = $this->tavern->open('pig', 't-1', ['ann', 'bob'], ['target' => 20], self::SEED);

        self::assertSame(20, $state->options->int('target'));
        self::assertSame(['seat' => 'ann', 'sequence' => 0], $this->timer->running('t-1'));
        self::assertSame(self::SEED, $this->tables->log('t-1')->seed);
    }

    public function testAnUnknownGameIsRefused(): void
    {
        try {
            $this->tavern->open('chess', 't-1', ['ann', 'bob']);
            self::fail('An unknown game is refused.');
        } catch (InvalidTavernGame) {
        }

        $this->expectException(UnknownTable::class);
        $this->tables->header('t-1');
    }

    public function testEachMoveTellsTheTimerWhoseTurnStarts(): void
    {
        $this->tavern->open('pig', 't-1', ['ann', 'bob']);

        $state = $this->tavern->play('t-1', new Move(Pig::HOLD, 'ann'), 0);

        self::assertSame(1, $state->sequence);
        self::assertSame(['seat' => 'bob', 'sequence' => 1], $this->timer->running('t-1'));
    }

    public function testTheEndOfTheGameStopsTheTimer(): void
    {
        $this->tavern->open('ship-captain-crew', 't-1', ['ann', 'bob'], [], self::SEED);

        for ($move = 0; null !== $this->timer->running('t-1'); ++$move) {
            $running = $this->timer->running('t-1');
            $this->tavern->play('t-1', new Move(ShipCaptainCrew::ROLL, $running['seat']));
            self::assertLessThan(10, $move);
        }

        self::assertTrue($this->tavern->state('t-1')->isOver());
        self::assertNotNull($this->tavern->ending('t-1', 'ann'));
    }

    public function testTheTimeoutOfTheSeatPlayingIsPlayed(): void
    {
        $this->tavern->open('pig', 't-1', ['ann', 'bob']);

        $state = $this->tavern->timeout('t-1', 'ann', 0);

        self::assertSame(1, $state->sequence);
        self::assertSame(TavernGame::TIMEOUT, $this->tables->log('t-1')->moves[0]->move->type);
        self::assertNull($this->tables->log('t-1')->moves[0]->move->seat);
        self::assertSame(['seat' => 'bob', 'sequence' => 1], $this->timer->running('t-1'));
    }

    public function testALateTimeoutIsIgnored(): void
    {
        $this->tavern->open('pig', 't-1', ['ann', 'bob']);
        $this->tavern->play('t-1', new Move(Pig::HOLD, 'ann'));

        self::assertSame(1, $this->tavern->timeout('t-1', 'ann', 0)->sequence);
        self::assertSame(1, $this->tavern->timeout('t-1', 'ann', 1)->sequence);
        self::assertSame(1, $this->tavern->timeout('t-1', 'bob', 0)->sequence);
        self::assertSame(2, $this->tavern->timeout('t-1', 'bob', 1)->sequence);
    }

    public function testATimeoutOnceTheGameIsOverIsIgnored(): void
    {
        $this->tavern->open('pig', 't-1', ['ann', 'bob'], ['target' => 20], self::SEED);
        $this->tables->play('t-1', new Move(TavernGame::TIMEOUT, null, ['seat' => 'ann']));

        for ($move = 0; !$this->tavern->state('t-1')->isOver(); ++$move) {
            $view = $this->tavern->view('t-1');
            self::assertIsString($view['turn']);
            $this->tavern->play('t-1', new Move($view['total'] >= 6 ? Pig::HOLD : Pig::ROLL, $view['turn']));
            self::assertLessThan(500, $move);
        }

        $over = $this->tavern->state('t-1');

        self::assertSame($over->sequence, $this->tavern->timeout('t-1', 'ann', $over->sequence)->sequence);
        self::assertNull($this->timer->running('t-1'));
    }

    public function testTheViewAndTheEnding(): void
    {
        $this->tavern->open('pig', 't-1', ['ann', 'bob'], ['target' => 20]);

        self::assertSame(['turn' => 'ann', 'scores' => ['ann' => 0, 'bob' => 0], 'total' => 0, 'last' => null, 'target' => 20], $this->tavern->view('t-1', 'bob'));
        self::assertNull($this->tavern->ending('t-1', 'ann'));
    }

    public function testTheEndingOfEachSeat(): void
    {
        $this->tavern->open('ship-captain-crew', 't-1', ['ann', 'bob'], [], self::SEED);

        while (!$this->tavern->state('t-1')->isOver()) {
            $view = $this->tavern->view('t-1');
            self::assertIsString($view['turn']);
            $this->tavern->play('t-1', new Move(ShipCaptainCrew::ROLL, $view['turn']));
        }

        $outcome = $this->tavern->state('t-1')->outcome;
        self::assertNotNull($outcome);

        foreach (['ann', 'bob'] as $seat) {
            $ending = $this->tavern->ending('t-1', $seat);
            self::assertNotNull($ending);
            self::assertSame(\in_array($seat, $outcome->winners, true), $ending->tone->celebrates());
        }

        self::assertSame(Tone::GoodGame, $this->tavern->ending('t-1')?->tone);
    }

    public function testWithoutATimerTheTablesStillPlay(): void
    {
        $games = new TavernGames(new Pig());
        $tavern = new TavernTables(new Tables($games->games(), new InMemoryLogStore()), $games);
        $tavern->open('pig', 't-1', ['ann', 'bob']);

        $timer = new NullTurnTimer();
        $timer->started('t-1', 'ann', 0);
        $timer->stopped('t-1');

        self::assertSame(1, $tavern->play('t-1', new Move(Pig::HOLD, 'ann'))->sequence);
    }

    public function testTheTimerForgetsAStoppedTable(): void
    {
        $this->timer->started('t-9', 'ann', 4);
        self::assertSame(['seat' => 'ann', 'sequence' => 4], $this->timer->running('t-9'));

        $this->timer->stopped('t-9');
        self::assertNull($this->timer->running('t-9'));
    }
}
