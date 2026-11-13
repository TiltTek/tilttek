<?php

declare(strict_types=1);

namespace Tilttek\GameEngine\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\GameEngine\Contract\Game;
use Tilttek\GameEngine\Engine;
use Tilttek\GameEngine\Exception\InvalidGame;
use Tilttek\GameEngine\GameDefinition;
use Tilttek\GameEngine\GameLog;
use Tilttek\GameEngine\Games;
use Tilttek\GameEngine\GameState;
use Tilttek\GameEngine\Move;
use Tilttek\GameEngine\Option;
use Tilttek\GameEngine\OptionValues;
use Tilttek\GameEngine\Outcome;
use Tilttek\GameEngine\Phase;
use Tilttek\GameEngine\Preferences;
use Tilttek\GameEngine\Randomness;
use Tilttek\GameEngine\Statistic;
use Tilttek\GameEngine\Statistics;
use Tilttek\GameEngine\StatisticScope;
use Tilttek\GameEngine\Store\InMemoryLogStore;
use Tilttek\GameEngine\Tables;
use Tilttek\GameEngine\Tests\Unit\Fixture\Race;
use Tilttek\GameEngine\TurnOrder;

/**
 * What a game offers beyond its rules: the preferences of each player, the statistics of its end, and the commitment
 * to the seed that lets anyone verify a replay.
 */
#[CoversClass(Preferences::class)]
#[CoversClass(OptionValues::class)]
#[CoversClass(Statistic::class)]
#[CoversClass(Statistics::class)]
#[CoversClass(StatisticScope::class)]
#[CoversClass(Outcome::class)]
#[CoversClass(GameDefinition::class)]
#[CoversClass(Engine::class)]
#[CoversClass(GameLog::class)]
#[CoversClass(Randomness::class)]
#[CoversClass(Tables::class)]
#[CoversClass(InMemoryLogStore::class)]
final class FinishTest extends TestCase
{
    private const string SEED = '00112233445566778899aabbccddeeff00112233445566778899aabbccddeeff';

    public function testPreferencesShapeTheViewOfASeatAndNeverTheState(): void
    {
        $race = new Race();
        $engine = new Engine();
        $table = $engine->start($race, 'table:1', ['ann', 'bob'], [], self::SEED);
        $table = $engine->play($race, $table, new Move('race.step', 'ann', ['steps' => 1]));
        $table = $engine->play($race, $table, new Move('race.step', 'bob', ['steps' => 3]));

        self::assertSame(['ann' => 1, 'bob' => 3], $engine->view($race, $table->state, 'ann')['positions']);
        self::assertSame(['bob' => 3, 'ann' => 1], $engine->view($race, $table->state, 'ann', ['leader-first' => true])['positions']);
        self::assertSame(['ann' => 1, 'bob' => 3], $engine->view($race, $table->state, null, ['leader-first' => false])['positions']);
        self::assertSame(['ann' => 1, 'bob' => 3], $table->state->data['positions']);

        $tables = new Tables(new Games($race), new InMemoryLogStore());
        $tables->open('race', 'table:2', ['ann', 'bob'], [], self::SEED);
        $tables->play('table:2', new Move('race.step', 'ann', ['steps' => 2]));
        self::assertSame(['ann' => 2, 'bob' => 0], $tables->view('table:2', 'bob', ['leader-first' => true])['positions']);

        $this->expectException(InvalidGame::class);
        $this->expectExceptionMessageIsOrContains('The preferences of the seat are refused: ');

        $engine->view($race, $table->state, 'ann', ['leader-first' => 'yes']);
    }

    public function testPreferencesAreResolvedAndReadLikeOptions(): void
    {
        $offered = [Option::boolean('confirm', true), Option::integer('size', 2, 1, 3), Option::choice('sort', 'suit', ['suit', 'rank'])];
        $preferences = Preferences::resolve($offered, ['size' => 3]);

        self::assertSame(['confirm' => true, 'size' => 3, 'sort' => 'suit'], $preferences->values);
        self::assertSame([true, 3, 'suit', 3], [$preferences->bool('confirm'), $preferences->int('size'), $preferences->string('sort'), $preferences->get('size')]);
        self::assertFalse(Preferences::resolve($offered, ['confirm' => false])->bool('confirm'));
        self::assertFalse(Preferences::resolve([Option::integer('one', 1, 1, 2)])->bool('one'));
        self::assertSame([], Preferences::resolve([])->values);

        foreach ([
            [static fn (): mixed => $preferences->get('none'), 'The game offers no preference "none".'],
            [static fn (): mixed => $preferences->int('sort'), 'The preference "sort" is not a whole number.'],
            [static fn (): mixed => $preferences->string('size'), 'The preference "size" is not a choice.'],
            [static fn (): mixed => Preferences::resolve($offered, ['other' => 1]), 'The preferences of the seat are refused: '],
            [static fn (): mixed => Preferences::resolve($offered, ['size' => 4]), 'The preferences of the seat are refused: '],
        ] as [$read, $message]) {
            try {
                $read();
                self::fail(\sprintf('"%s" was accepted.', $message));
            } catch (InvalidGame $exception) {
                self::assertStringStartsWith($message, $exception->getMessage());
                self::assertSame($message, substr($exception->getMessage(), 0, \strlen($message)));
            }
        }
    }

    public function testADefinitionDeclaresItsPreferencesAndStatisticsOnce(): void
    {
        $phases = [new Phase('play')];
        $definition = new GameDefinition('cards', $phases, preferences: [Option::boolean('confirm', true)], statistics: [Statistic::table('rounds'), Statistic::seat('points')]);

        self::assertSame('confirm', $definition->preferences[0]->name);
        self::assertSame(StatisticScope::Table, $definition->statistic('rounds', StatisticScope::Table)?->scope);
        self::assertSame('points', $definition->statistic('points', StatisticScope::Seat)?->name);
        self::assertNull($definition->statistic('points', StatisticScope::Table));
        self::assertNull($definition->statistic('rounds', StatisticScope::Seat));
        self::assertNull($definition->statistic('none', StatisticScope::Seat));
        self::assertSame([], new GameDefinition('cards', $phases)->statistics);
        self::assertSame([], new GameDefinition('cards', $phases)->preferences);

        foreach ([
            [static fn (): GameDefinition => new GameDefinition('cards', $phases, preferences: [Option::boolean('a', true), Option::boolean('a', false)]), 'The game "cards" has two of the preference "a".'],
            [static fn (): GameDefinition => new GameDefinition('cards', $phases, statistics: [Statistic::table('a'), Statistic::seat('a')]), 'The game "cards" has two of the statistic "a".'],
            [static fn (): Statistic => Statistic::seat('A'), 'The name of a statistic "A" must be'],
            [static fn (): Statistic => Statistic::table('a b'), 'The name of a statistic "a b" must be'],
        ] as [$make, $message]) {
            try {
                $make();
                self::fail(\sprintf('"%s" was accepted.', $message));
            } catch (InvalidGame $exception) {
                self::assertStringStartsWith($message, $exception->getMessage());
            }
        }
    }

    public function testTheOutcomeReportsTheStatisticsTheGameDeclares(): void
    {
        $statistics = new Statistics(['rounds' => 4], ['ann' => ['points' => 12], 'bob' => ['points' => 0]]);

        self::assertSame(4, $statistics->ofTable('rounds'));
        self::assertNull($statistics->ofTable('points'));
        self::assertSame(12, $statistics->ofSeat('ann', 'points'));
        self::assertSame(0, $statistics->ofSeat('bob', 'points'));
        self::assertNull($statistics->ofSeat('cy', 'points'));
        self::assertNull($statistics->ofSeat('ann', 'rounds'));
        self::assertFalse($statistics->isEmpty());
        self::assertFalse(new Statistics(['a' => 1])->isEmpty());
        self::assertFalse(new Statistics([], ['ann' => []])->isEmpty());
        self::assertTrue(new Statistics()->isEmpty());
        self::assertTrue(Outcome::won(['ann'], 'x')->statistics->isEmpty());
        self::assertSame($statistics, Outcome::won(['ann'], 'x', $statistics)->statistics);
        self::assertSame($statistics, Outcome::ended('x', $statistics)->statistics);

        $engine = new Engine();
        $table = $engine->start($this->ending(new Statistics(['rounds' => 1], ['ann' => ['points' => 3]])), 'table:1', ['ann']);
        $over = $engine->play($this->ending(new Statistics(['rounds' => 1], ['ann' => ['points' => 3]])), $table, new Move('end.now', 'ann'));
        self::assertSame(3, $over->state->outcome?->statistics->ofSeat('ann', 'points'));

        foreach ([
            [new Statistics(['points' => 1]), 'The game "ending" reports the statistic of the table "points", which it does not declare.'],
            [new Statistics([], ['ann' => ['rounds' => 1]]), 'The game "ending" reports the statistic of a seat "rounds", which it does not declare.'],
            [new Statistics([], ['bob' => ['points' => 1]]), 'The game "ending" reports statistics of the seat "bob", which is not at the table.'],
        ] as [$reported, $message]) {
            try {
                $engine->play($this->ending($reported), $table, new Move('end.now', 'ann'));
                self::fail(\sprintf('"%s" was accepted.', $message));
            } catch (InvalidGame $exception) {
                self::assertSame($message, $exception->getMessage());
            }
        }
    }

    public function testAReplayIsVerifiedAgainstTheCommitmentToTheSeed(): void
    {
        $race = new Race();
        $engine = new Engine();
        $table = $engine->start($race, 'table:1', ['ann', 'bob'], [], self::SEED);
        $table = $engine->play($race, $table, new Move('race.roll', 'ann'));

        $commitment = $table->log->commitment();

        self::assertSame(hash('sha256', self::SEED), $commitment);
        self::assertSame($commitment, Randomness::commitment(self::SEED));
        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/D', $commitment);
        self::assertEquals($table->state, $engine->verify($race, $table->log, $commitment));

        // Another seed draws other values: the log alone cannot tell, the commitment shown at the opening does.
        $other = new GameLog('table:1', 'race', '1', str_repeat('ab', 32), ['ann', 'bob'], $table->log->options, $table->log->moves);
        self::assertNotEquals($table->state->data, $engine->verify($race, $other, $other->commitment())->data);

        foreach ([
            [$other, $commitment, 'The seed of the table "table:1" is not the one it committed to.'],
            [$table->log, strtoupper($commitment), 'The seed of the table "table:1" is not the one it committed to.'],
        ] as [$log, $claimed, $message]) {
            try {
                $engine->verify($race, $log, $claimed);
                self::fail(\sprintf('"%s" was accepted.', $message));
            } catch (InvalidGame $exception) {
                self::assertStringStartsWith($message, $exception->getMessage());
            }
        }
    }

    /**
     * A game of one move that ends it, reporting the statistics given.
     */
    private function ending(Statistics $statistics): Game
    {
        return new readonly class($statistics) implements Game {
            private GameDefinition $definition;

            public function __construct(private Statistics $statistics)
            {
                $this->definition = new GameDefinition('ending', [new Phase('play', TurnOrder::Sequential)], statistics: [Statistic::table('rounds'), Statistic::seat('points')]);
            }

            public function definition(): GameDefinition
            {
                return $this->definition;
            }

            public function setup(GameState $state, Randomness $random): array
            {
                return ['over' => false];
            }

            public function allows(GameState $state, Move $move): bool
            {
                return true;
            }

            public function play(GameState $state, Move $move, Randomness $random): array
            {
                return ['over' => true];
            }

            public function enter(GameState $state, Randomness $random): array
            {
                return $state->data;
            }

            public function outcome(GameState $state): ?Outcome
            {
                return true === $state->data['over'] ? Outcome::ended('done', $this->statistics) : null;
            }

            public function view(GameState $state, ?string $seat, Preferences $preferences): array
            {
                return $state->data;
            }
        };
    }
}
