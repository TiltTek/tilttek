<?php

declare(strict_types=1);

namespace Tilttek\MiniGames\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\GameEngine\Exception\UnknownTable;
use Tilttek\GameEngine\Randomness;
use Tilttek\GameEngine\Store\InMemoryLogStore;
use Tilttek\GameEngine\Tables;
use Tilttek\MiniGames\DailyGames;
use Tilttek\MiniGames\DailyScore;
use Tilttek\MiniGames\DailySeed;
use Tilttek\MiniGames\Day;
use Tilttek\MiniGames\Exception\InvalidMiniGame;
use Tilttek\MiniGames\Exception\SeedNotRevealable;
use Tilttek\MiniGames\Game\BullsAndCows;
use Tilttek\MiniGames\Game\HigherOrLower;
use Tilttek\MiniGames\MiniGames;
use Tilttek\MiniGames\ScoreOrder;
use Tilttek\MiniGames\Store\InMemoryScoreSink;
use Tilttek\MiniGames\Store\NullScoreSink;
use Tilttek\MiniGames\Tone;

#[CoversClass(DailyGames::class)]
#[CoversClass(SeedNotRevealable::class)]
#[CoversClass(InMemoryScoreSink::class)]
#[CoversClass(NullScoreSink::class)]
final class DailyGamesTest extends TestCase
{
    private const string SECRET = 'a secret of the site, 32 bytes or more';

    private InMemoryScoreSink $scores;

    private Tables $tables;

    private DailyGames $daily;

    private Day $day;

    protected function setUp(): void
    {
        $games = new MiniGames(new BullsAndCows(), new HigherOrLower());
        $this->scores = new InMemoryScoreSink();
        $this->tables = new Tables($games->games(), new InMemoryLogStore());
        $this->daily = new DailyGames($this->tables, $games, new DailySeed(self::SECRET), $this->scores);
        $this->day = Day::fromString('2026-11-24');
    }

    public function testTheTableOfAPlayerForADay(): void
    {
        self::assertSame('daily:higher-or-lower:2026-11-24:u-101', DailyGames::tableId('higher-or-lower', $this->day, 'u-101'));
    }

    public function testATableIsOpenedWithTheSeedOfTheDay(): void
    {
        $state = $this->daily->start('higher-or-lower', $this->day, 'u-1');
        $table = DailyGames::tableId('higher-or-lower', $this->day, 'u-1');

        self::assertSame(['u-1'], $state->seats);
        self::assertSame(0, $state->sequence);
        self::assertSame(Randomness::commitment(new DailySeed(self::SECRET)->for('higher-or-lower', $this->day)), $this->tables->commitment($table));
    }

    public function testEveryPlayerOfADayGetsTheSameGame(): void
    {
        $this->daily->start('higher-or-lower', $this->day, 'u-1');
        $this->daily->start('higher-or-lower', $this->day, 'u-2');
        $this->daily->start('higher-or-lower', Day::fromString('2026-11-25'), 'u-1');

        $first = $this->tables->state(DailyGames::tableId('higher-or-lower', $this->day, 'u-1'))->data;
        $second = $this->tables->state(DailyGames::tableId('higher-or-lower', $this->day, 'u-2'))->data;
        $tomorrow = $this->tables->state(DailyGames::tableId('higher-or-lower', Day::fromString('2026-11-25'), 'u-1'))->data;

        self::assertSame($first, $second);
        self::assertNotSame($first, $tomorrow);
    }

    public function testStartingAgainGivesTheSameTable(): void
    {
        $this->daily->start('higher-or-lower', $this->day, 'u-1');
        $this->daily->play('higher-or-lower', $this->day, 'u-1', HigherOrLower::CALL, ['call' => 'higher']);

        self::assertSame(1, $this->daily->start('higher-or-lower', $this->day, 'u-1')->sequence);
    }

    public function testTheScoreGoesToTheSinkOnceTheGameEnds(): void
    {
        $this->daily->start('bulls-and-cows', $this->day, 'u-1');
        $secret = $this->tables->state(DailyGames::tableId('bulls-and-cows', $this->day, 'u-1'))->data['secret'];
        self::assertIsString($secret);
        $wrong = $this->wrong($secret);

        $this->daily->play('bulls-and-cows', $this->day, 'u-1', BullsAndCows::GUESS, ['guess' => $wrong], 0);
        self::assertSame([], $this->scores->scores());
        self::assertNull($this->daily->ending('bulls-and-cows', $this->day, 'u-1'));

        $state = $this->daily->play('bulls-and-cows', $this->day, 'u-1', BullsAndCows::GUESS, ['guess' => $secret]);
        self::assertTrue($state->isOver());

        $scores = $this->scores->scores();
        self::assertCount(1, $scores);
        self::assertSame('bulls-and-cows', $scores[0]->game);
        self::assertSame('2026-11-24', $scores[0]->day->value);
        self::assertSame('u-1', $scores[0]->player);
        self::assertTrue($scores[0]->won);
        self::assertSame(2, $scores[0]->score);
        self::assertSame(ScoreOrder::LowerIsBetter, $scores[0]->order);

        $ending = $this->daily->ending('bulls-and-cows', $this->day, 'u-1', 2);
        self::assertNotNull($ending);
        self::assertSame(Tone::Victory, $ending->tone);
        self::assertFalse($ending->record);
        self::assertSame("Bulls and Cows 1 2/10\n\n\u{1F7E8}\u{1F7E8}\u{2B1B}\u{2B1B}\n\u{1F7E9}\u{1F7E9}\u{1F7E9}\u{1F7E9}", $this->daily->share('bulls-and-cows', $this->day, 'u-1')->toText('Bulls and Cows 1'));
        self::assertSame($secret, $this->daily->view('bulls-and-cows', $this->day, 'u-1')['code']);
    }

    public function testALostGameIsReportedWithItsScore(): void
    {
        $this->daily->start('higher-or-lower', $this->day, 'u-1');
        $numbers = $this->tables->state(DailyGames::tableId('higher-or-lower', $this->day, 'u-1'))->data['numbers'];
        self::assertIsArray($numbers);
        $wrong = $numbers[1] > $numbers[0] ? 'lower' : 'higher';

        $this->daily->play('higher-or-lower', $this->day, 'u-1', HigherOrLower::CALL, ['call' => $wrong]);

        $scores = $this->scores->scores();
        self::assertCount(1, $scores);
        self::assertFalse($scores[0]->won);
        self::assertSame(0, $scores[0]->score);
        self::assertSame(ScoreOrder::HigherIsBetter, $scores[0]->order);
        self::assertSame(Tone::Completed, $this->daily->ending('higher-or-lower', $this->day, 'u-1')?->tone);
    }

    public function testTheViewIsThePlayersOwn(): void
    {
        $this->daily->start('bulls-and-cows', $this->day, 'u-1');

        self::assertSame(['length' => 4, 'guesses' => [], 'left' => 10, 'code' => null], $this->daily->view('bulls-and-cows', $this->day, 'u-1'));
    }

    public function testTheSeedIsShownOnceTheDayIsOver(): void
    {
        $this->finishBullsAndCows();

        $log = $this->daily->finishedLog('bulls-and-cows', $this->day, 'u-1', Day::fromString('2026-11-25'));

        self::assertSame(new DailySeed(self::SECRET)->for('bulls-and-cows', $this->day), $log->seed);
    }

    public function testTheSeedIsNotShownTheSameDay(): void
    {
        $this->finishBullsAndCows();

        $this->expectException(SeedNotRevealable::class);
        $this->expectExceptionMessageIsOrContains('The seed of the daily game "bulls-and-cows" of 2026-11-24 is shown once the day is over.');

        $this->daily->finishedLog('bulls-and-cows', $this->day, 'u-1', $this->day);
    }

    public function testTheSeedIsNotShownBeforeTheDay(): void
    {
        $this->expectException(SeedNotRevealable::class);

        $this->daily->finishedLog('bulls-and-cows', $this->day, 'u-1', Day::fromString('2026-11-23'));
    }

    public function testAnUnknownGameIsRefusedBeforeAnyTableIsOpened(): void
    {
        try {
            $this->daily->start('chess', $this->day, 'u-1');
            self::fail('An unknown game is refused.');
        } catch (InvalidMiniGame) {
        }

        $this->expectException(UnknownTable::class);
        $this->tables->header(DailyGames::tableId('chess', $this->day, 'u-1'));
    }

    public function testAMoveOfAnUnknownGameIsRefused(): void
    {
        $this->expectException(InvalidMiniGame::class);

        $this->daily->play('chess', $this->day, 'u-1', 'chess.move');
    }

    public function testTheNullSinkKeepsNothing(): void
    {
        $games = new MiniGames(new HigherOrLower());
        $daily = new DailyGames(new Tables($games->games(), new InMemoryLogStore()), $games, new DailySeed(self::SECRET));
        $daily->start('higher-or-lower', $this->day, 'u-1');

        $view = $daily->view('higher-or-lower', $this->day, 'u-1');
        self::assertIsArray($view['shown']);

        $state = $daily->play('higher-or-lower', $this->day, 'u-1', HigherOrLower::CALL, ['call' => 'higher']);
        new NullScoreSink()->record(new DailyScore('higher-or-lower', $this->day, 'u-1', false, 0, ScoreOrder::HigherIsBetter));

        self::assertSame(1, $state->sequence);
    }

    private function finishBullsAndCows(): void
    {
        $this->daily->start('bulls-and-cows', $this->day, 'u-1');
        $secret = $this->tables->state(DailyGames::tableId('bulls-and-cows', $this->day, 'u-1'))->data['secret'];
        self::assertIsString($secret);
        $this->daily->play('bulls-and-cows', $this->day, 'u-1', BullsAndCows::GUESS, ['guess' => $secret]);
    }

    /**
     * A guess with the two first digits of the secret swapped, two digits found elsewhere, and two digits not in it.
     */
    private function wrong(string $secret): string
    {
        $others = array_values(array_diff(str_split('0123456789'), str_split($secret)));

        return $secret[1].$secret[0].$others[0].$others[1];
    }
}
