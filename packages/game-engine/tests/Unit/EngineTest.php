<?php

declare(strict_types=1);

namespace Tilttek\GameEngine\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\GameEngine\Engine;
use Tilttek\GameEngine\Exception\GameOver;
use Tilttek\GameEngine\Exception\IllegalMove;
use Tilttek\GameEngine\Exception\InvalidGame;
use Tilttek\GameEngine\GameLog;
use Tilttek\GameEngine\GameState;
use Tilttek\GameEngine\LoggedMove;
use Tilttek\GameEngine\Move;
use Tilttek\GameEngine\Outcome;
use Tilttek\GameEngine\Scene\RoleplayingScene;
use Tilttek\GameEngine\Statistics;
use Tilttek\GameEngine\Step;
use Tilttek\GameEngine\Table;
use Tilttek\GameEngine\Tests\Unit\Fixture\Race;

#[CoversClass(Engine::class)]
#[CoversClass(Table::class)]
#[CoversClass(GameState::class)]
#[CoversClass(Outcome::class)]
#[CoversClass(IllegalMove::class)]
#[CoversClass(GameOver::class)]
#[CoversClass(Step::class)]
final class EngineTest extends TestCase
{
    private const string SEED = '00112233445566778899aabbccddeeff00112233445566778899aabbccddeeff';

    public function testATableOpensWithItsSeedOptionsAndTheGamesSetup(): void
    {
        $table = new Engine()->start(new Race(), 'table:1', ['ann', 'bob'], ['target' => 12], self::SEED);

        self::assertSame(['game_id' => 'table:1', 'game' => 'race', 'version' => '1', 'seed' => self::SEED, 'seats' => ['ann', 'bob'], 'options' => ['target' => 12, 'track' => 'short'], 'moves' => []], $table->log->toArray());
        $state = $table->state;
        self::assertSame(['table:1', 'race', 0, ['ann', 'bob'], 'race'], [$state->gameId, $state->game, $state->sequence, $state->seats, $state->phase]);
        self::assertSame(12, $state->options->int('target'));
        self::assertSame(['ann'], $state->turn->toAct());
        self::assertSame(['ann' => 0, 'bob' => 0], $state->data['positions']);
        self::assertSame([], $state->initiative);
        self::assertFalse($state->isOver());
        self::assertNull($state->outcome);
        self::assertEquals($state, new Engine()->start(new Race(), 'table:1', ['ann', 'bob'], ['target' => 12], self::SEED)->state);
        self::assertNotEquals($state->data['secrets'], new Engine()->start(new Race(), 'table:1', ['ann', 'bob'], ['target' => 12], str_repeat('ab', 32))->state->data['secrets']);
        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', new Engine()->start(new Race(), 'table:1', ['ann', 'bob'])->log->seed);
    }

    public function testTheSeatsAreCountedAndUnique(): void
    {
        foreach ([
            [['ann'], 'The game "race" seats 2 to 4 players, not 1.'],
            [['a', 'b', 'c', 'd', 'e'], 'The game "race" seats 2 to 4 players, not 5.'],
            [['ann', 'ann'], 'A seat is given twice.'],
            [['ann', '7'], 'The identifier of a seat "7" must be an ASCII letter'],
        ] as [$seats, $message]) {
            try {
                new Engine()->start(new Race(), 'table:1', $seats, [], self::SEED);
                self::fail('The seats were accepted.');
            } catch (InvalidGame $exception) {
                self::assertStringStartsWith($message, $exception->getMessage());
            }
        }

        self::assertCount(4, new Engine()->start(new Race(), 'table:1', ['a', 'b', 'c', 'd'], [], self::SEED)->state->seats);
    }

    public function testMovesFollowTheTurnsAndEndTheGame(): void
    {
        $engine = new Engine();
        $race = new Race();
        $table = $engine->start($race, 'table:1', ['ann', 'bob'], ['target' => 10], self::SEED);
        $table = $engine->play($race, $table, new Move('race.step', 'ann', ['steps' => 3]));

        self::assertSame(1, $table->state->sequence);
        self::assertSame(['bob'], $table->state->turn->toAct());
        self::assertSame(['ann' => 3, 'bob' => 0], $table->state->data['positions']);
        self::assertEquals(new LoggedMove(1, new Move('race.step', 'ann', ['steps' => 3]), false), $table->log->last());

        $table = $engine->play($race, $table, new Move('race.taunt', 'ann'));
        self::assertSame(['bob'], $table->state->turn->toAct());
        self::assertSame(1, $table->state->data['taunts']);

        $table = $engine->play($race, $table, new Move('race.roll', 'bob'));
        self::assertTrue($table->log->last()?->drew);
        self::assertSame(['ann'], $table->state->turn->toAct());
        self::assertSame(2, $table->state->turn->round);

        foreach ([
            [new Move('race.step', 'bob', ['steps' => 1]), 'It is not the turn of the seat "bob".'],
            [Move::endTurn('bob'), 'It is not the turn of the seat "bob".'],
            [new Move('race.step', 'zed', ['steps' => 1]), 'The seat "zed" is not at the table "table:1".'],
            [new Move('race.step', 'ann', ['steps' => 4]), 'A step is 1 to 3.'],
            [new Move('race.jump', 'ann'), 'The game refuses the move "race.jump" now.'],
            [Move::transition('end', 'ann'), 'The game refuses the move "engine.transition" now.'],
        ] as [$move, $message]) {
            try {
                $engine->play($race, $table, $move);
                self::fail('The move was accepted.');
            } catch (IllegalMove $exception) {
                self::assertSame($message, $exception->getMessage());
            }
        }

        $table = $engine->play($race, $table, Move::endTurn(null));
        self::assertSame(['bob'], $table->state->turn->toAct());
        $table = $engine->play($race, $table, new Move('race.step', null, ['steps' => 3]));
        self::assertSame(['ann'], $table->state->turn->toAct());

        while (!$table->state->isOver()) {
            $table = $engine->play($race, $table, new Move('race.step', $table->state->turn->toAct()[0], ['steps' => 3]));
        }

        self::assertEquals(Outcome::won([(string) $table->log->last()?->move->seat], 'target-reached', new Statistics(
            ['rounds' => 5],
            ['ann' => ['position' => 9], 'bob' => ['position' => 12]],
        )), $table->state->outcome);
        self::assertTrue($table->state->outcome?->hasWinners());

        $this->expectException(IllegalMove::class);
        $this->expectExceptionMessageIsOrContains('The game at the table "table:1" is over.');

        $engine->play($race, $table, new Move('race.taunt', 'bob'));
    }

    public function testAReplayedLogGivesTheSameStateAsThePlay(): void
    {
        $engine = new Engine();
        $race = new Race();
        $table = $engine->start($race, 'table:9', ['ann', 'bob', 'cy'], ['target' => 60, 'track' => 'long'], self::SEED);

        foreach (range(1, 30) as $turn) {
            $seat = $table->state->turn->toAct()[0];
            $table = $engine->play($race, $table, 0 === $turn % 4 ? new Move('race.step', $seat, ['steps' => 2]) : new Move('race.roll', $seat));

            if (0 === $turn % 7) {
                $table = $engine->play($race, $table, new Move('race.taunt', 'cy'));
            }
        }

        $json = json_encode($table->log->toArray(), \JSON_THROW_ON_ERROR);
        $log = GameLog::fromArray((array) json_decode($json, true, 512, \JSON_THROW_ON_ERROR));

        self::assertEquals($table->log, $log);
        self::assertEquals($table->state, $engine->replay($race, $log));
        self::assertEquals($table->state, $engine->replay(new Race(), $log));
        self::assertNotEquals($table->state->data, $engine->replay($race, new GameLog('table:9', 'race', '1', str_repeat('cd', 32), $log->seats, $log->options, $log->moves))->data);
    }

    public function testALogOfAnotherGameOrVersionOrThatDrawsDifferentlyIsNotReplayed(): void
    {
        $engine = new Engine();
        $table = $engine->play(new Race(), $engine->start(new Race(), 'table:1', ['ann', 'bob'], [], self::SEED), new Move('race.step', 'ann', ['steps' => 1]));

        foreach ([
            [new Race(version: '2'), 'The log of the table "table:1" is of the game "race" version "1", not "race" version "2".'],
            [new Race(stepsDraw: true), 'The move 1 of the table "table:1" draws differently when it is replayed: the game is not pure, or its rules changed.'],
        ] as [$game, $message]) {
            try {
                $engine->replay($game, $table->log);
                self::fail('The log was replayed.');
            } catch (InvalidGame $exception) {
                self::assertSame($message, $exception->getMessage());
            }
        }

        $foreign = new GameLog('table:1', 'chess', '1', self::SEED, ['ann', 'bob']);

        try {
            $engine->replay(new Race(), $foreign);
            self::fail('A log of another game was replayed.');
        } catch (InvalidGame $invalidGame) {
            self::assertStringStartsWith('The log of the table "table:1" is of the game "chess"', $invalidGame->getMessage());
        }

        $broken = new GameLog('table:1', 'race', '1', self::SEED, ['ann', 'bob'], [], [new LoggedMove(1, new Move('race.step', 'bob', ['steps' => 1]))]);

        $this->expectException(InvalidGame::class);
        $this->expectExceptionMessageIsOrContains('The move 1 of the table "table:1" no longer applies: It is not the turn of the seat "bob".');

        $engine->replay(new Race(), $broken);
    }

    public function testTheLastMoveIsTakenBackWhenItRevealedNothing(): void
    {
        $engine = new Engine();
        $race = new Race();
        $opened = $engine->start($race, 'table:1', ['ann', 'bob'], [], self::SEED);
        $stepped = $engine->play($race, $opened, new Move('race.step', 'ann', ['steps' => 2]));
        $undone = $engine->undo($race, $stepped, 'ann');

        self::assertSame(0, $undone->log->sequence());
        self::assertEquals($opened->state, $undone->state);
        self::assertEquals($opened->state, $engine->undo($race, $stepped)->state);

        foreach ([
            [$race, $opened, 'ann', 'The table has no move to take back.'],
            [$race, $stepped, 'bob', 'The last move is not the seat "bob"\'s to take back.'],
            [$race, $engine->play($race, $stepped, new Move('race.roll', 'bob')), null, 'The last move drew at random: what it revealed cannot be taken back.'],
            [new Race(undo: false), $stepped, 'ann', 'The game does not take moves back.'],
        ] as [$game, $table, $seat, $message]) {
            try {
                $engine->undo($game, $table, $seat);
                self::fail('The move was taken back.');
            } catch (IllegalMove $exception) {
                self::assertSame($message, $exception->getMessage());
            }
        }
    }

    public function testTheTakeBackOfAMoveIsCheckedFromTheStateAndTheLastMove(): void
    {
        $engine = new Engine();
        $race = new Race();
        $opened = $engine->start($race, 'table:1', ['ann', 'bob'], [], self::SEED);
        $stepped = $engine->play($race, $opened, new Move('race.step', 'ann', ['steps' => 2]));

        $engine->checkUndo($race, $stepped->state, $stepped->log->last(), 'ann');
        $engine->checkUndo($race, $stepped->state, $stepped->log->last());

        $this->expectException(IllegalMove::class);

        $engine->checkUndo($race, $stepped->state, $stepped->log->last(), 'bob');
    }

    public function testNoMoveIsTakenBackOnceTheGameIsOver(): void
    {
        $engine = new Engine();
        $race = new Race();
        $table = $engine->start($race, 'table:1', ['ann', 'bob'], ['target' => 10], self::SEED);

        foreach ([3, 3, 3, 3, 3, 3] as $steps) {
            $table = $engine->play($race, $table, new Move('race.step', $table->state->turn->toAct()[0], ['steps' => $steps]));
        }

        $before = $engine->play($race, $table, new Move('race.taunt', 'bob'));
        self::assertFalse($before->state->isOver());
        self::assertSame(6, $engine->undo($race, $before, 'bob')->log->sequence());

        $over = $engine->play($race, $table, new Move('race.step', 'ann', ['steps' => 1]));
        self::assertTrue($over->state->isOver());

        foreach ([['ann', $race], [null, $race], ['bob', $race], ['ann', new Race(undo: false)]] as [$seat, $game]) {
            try {
                $engine->undo($game, $over, $seat);
                self::fail('A move of a finished game was taken back.');
            } catch (GameOver $exception) {
                self::assertSame('The game at the table "table:1" is over: its seed may be shown, so no move is taken back.', $exception->getMessage());
            }
        }
    }

    public function testAStepAndAnAdvanceFollowTheLogTheyAreGiven(): void
    {
        $engine = new Engine();
        $race = new Race();
        $opened = $engine->start($race, 'table:1', ['ann', 'bob'], [], self::SEED);
        $header = $opened->log->header();

        $step = $engine->step($race, $header, $opened->state, new Move('race.roll', 'ann'));
        self::assertSame(1, $step->move->sequence);
        self::assertTrue($step->move->drew);
        self::assertSame(1, $step->state->sequence);
        self::assertEquals($engine->play($race, $opened, new Move('race.roll', 'ann'))->state, $step->state);

        $played = $engine->play($race, $engine->play($race, $opened, new Move('race.step', 'ann', ['steps' => 2])), new Move('race.step', 'bob', ['steps' => 1]));
        $first = $engine->play($race, $opened, new Move('race.step', 'ann', ['steps' => 2]));
        self::assertEquals($played->state, $engine->advance($race, $played->log->header(), $first->state, [$played->log->move(2)]));
        self::assertEquals($played->state, $engine->advance($race, $played->log->header(), $engine->setup($race, $played->log->header()), $played->log->moves));
        self::assertEquals($first->state, $engine->advance($race, $played->log->header(), $first->state, []));

        $other = $engine->start($race, 'table:2', ['ann', 'bob'], [], self::SEED);

        foreach ([
            [static fn (): mixed => $engine->step($race, $played->log->header(), $first->state, Move::endTurn(null)), 'The state of the table "table:1" after 1 moves is not the one of the log of the table "table:1" after 2 moves.'],
            [static fn (): mixed => $engine->step($race, $other->log->header(), $opened->state, Move::endTurn(null)), 'The state of the table "table:1" after 0 moves is not the one of the log of the table "table:2" after 0 moves.'],
            [static fn (): mixed => $engine->advance($race, $played->log->header(), $opened->state, [$played->log->move(2)]), 'The move 2 of the table "table:1" does not follow its 0 moves.'],
            [static fn (): mixed => $engine->advance($race, $first->log->header(), $first->state, [$played->log->move(2)]), 'The move 2 of the table "table:1" does not follow its 1 moves.'],
            [static fn (): mixed => $engine->advance($race, $other->log->header(), $opened->state, []), 'The state of the table "table:1" is not one of the log of the table "table:2".'],
            [static fn (): mixed => $engine->advance(new RoleplayingScene(), new GameLog('table:1', 'roleplaying-scene', '1', self::SEED, ['gm'])->header(), $opened->state, []), 'The state of the table "table:1" is not one of the log of the table "table:1".'],
            [static fn (): mixed => $engine->setup(new Race(version: '2'), $header), 'The log of the table "table:1" is of the game "race" version "1", not "race" version "2".'],
        ] as [$call, $message]) {
            try {
                $call();
                self::fail('The call was accepted.');
            } catch (InvalidGame $exception) {
                self::assertSame($message, $exception->getMessage());
            }
        }
    }

    public function testASeatSeesItsOwnSecretsOnly(): void
    {
        $engine = new Engine();
        $race = new Race();
        $state = $engine->start($race, 'table:1', ['ann', 'bob'], [], self::SEED)->state;
        $secrets = $state->data['secrets'];
        self::assertIsArray($secrets);

        self::assertSame(['ann' => $secrets['ann']], $engine->view($race, $state, 'ann')['secrets']);
        self::assertSame([], $engine->view($race, $state)['secrets']);

        $this->expectExceptionMessageIsOrContains('The seat "zed" is not at the table "table:1".');

        $engine->view($race, $state, 'zed');
    }

    public function testAnOutcomeWithoutWinnersEndsTheGame(): void
    {
        self::assertFalse(Outcome::ended('scene-ended')->hasWinners());
        self::assertSame('scene-ended', Outcome::ended('scene-ended')->reason);
        self::assertSame(['ann'], Outcome::won(['ann'], 'x')->winners);
    }
}
