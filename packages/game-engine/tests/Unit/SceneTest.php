<?php

declare(strict_types=1);

namespace Tilttek\GameEngine\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\GameEngine\Engine;
use Tilttek\GameEngine\Exception\IllegalMove;
use Tilttek\GameEngine\Move;
use Tilttek\GameEngine\Outcome;
use Tilttek\GameEngine\Scene\RoleplayingScene;
use Tilttek\GameEngine\Statistics;
use Tilttek\GameEngine\Table;

#[CoversClass(RoleplayingScene::class)]
#[CoversClass(Engine::class)]
final class SceneTest extends TestCase
{
    private const string SEED = 'ffeeddccbbaa99887766554433221100ffeeddccbbaa99887766554433221100';

    public function testASceneMovesFromExplorationToAnEncounterInInitiativeOrderAndBack(): void
    {
        $scene = new RoleplayingScene();
        $engine = new Engine();
        $table = $engine->start($scene, 'scene:1', ['gm', 'ann', 'bob', 'cy'], [], self::SEED);

        self::assertSame('exploration', $table->state->phase);
        self::assertSame(['gm', 'ann', 'bob', 'cy'], $table->state->turn->toAct());
        self::assertSame(['actions' => ['gm' => 0, 'ann' => 0, 'bob' => 0, 'cy' => 0], 'notes' => 0, 'encounters' => 0, 'ended' => false], $table->state->data);

        $table = $this->play($engine, $scene, $table, new Move('scene.act', 'bob', ['summary' => 'Searches the room.']), new Move('scene.act', 'bob'));
        self::assertSame(['gm' => 0, 'ann' => 0, 'bob' => 2, 'cy' => 0], $table->state->data['actions']);

        $table = $this->play($engine, $scene, $table, Move::initiative(['ann' => 14, 'bob' => 9, 'cy' => 14], 'gm'), Move::transition('start-encounter', 'gm'));
        self::assertSame('encounter', $table->state->phase);
        self::assertSame(['ann', 'cy', 'bob', 'gm'], $table->state->turn->order);
        self::assertSame(1, $table->state->data['encounters']);
        self::assertSame(['ann' => 14, 'bob' => 9, 'cy' => 14], $table->state->initiative);

        $table = $this->play($engine, $scene, $table, new Move('scene.act', 'ann'), Move::endTurn('ann'), Move::delay('cy', 'gm'));
        self::assertSame(['ann', 'bob', 'gm', 'cy'], $table->state->turn->order);
        self::assertSame(['bob'], $table->state->turn->toAct());

        $table = $this->play($engine, $scene, $table, new Move('scene.note', 'cy', ['text' => 'The torch is out.']), Move::initiative(['gm' => 30], 'gm'));
        self::assertSame(1, $table->state->data['notes']);
        self::assertSame(['gm', 'ann', 'cy', 'bob'], $table->state->turn->order);
        self::assertSame(['gm'], $table->state->turn->toAct());

        $table = $this->play($engine, $scene, $table, Move::transition('end-encounter', 'gm'), Move::transition('start-encounter', 'gm'));
        self::assertSame(2, $table->state->data['encounters']);
        $table = $this->play($engine, $scene, $table, Move::transition('explore', 'gm'), Move::initiative(['bob' => 1], 'gm'));
        self::assertSame('exploration', $table->state->phase);
        self::assertSame(['gm', 'ann', 'bob', 'cy'], $table->state->turn->order);
        self::assertSame(1, $table->state->initiative['bob']);

        $table = $this->play($engine, $scene, $table, new Move('scene.end', 'gm'));
        self::assertEquals(Outcome::ended('scene-ended', new Statistics(
            ['encounters' => 2, 'notes' => 1],
            ['gm' => ['actions' => 0], 'ann' => ['actions' => 1], 'bob' => ['actions' => 2], 'cy' => ['actions' => 0]],
        )), $table->state->outcome);
        self::assertSame($table->state->data, $engine->view($scene, $table->state, 'ann'));
        self::assertEquals($table->state, $engine->replay($scene, $table->log));
    }

    public function testTheLeaderAloneChangesThePhaseUnlessTheTableSaysOtherwise(): void
    {
        $scene = new RoleplayingScene();
        $engine = new Engine();
        $table = $engine->start($scene, 'scene:1', ['gm', 'ann'], [], self::SEED);

        foreach ([
            [Move::transition('start-encounter', 'ann'), 'The game refuses the move "engine.transition" now.'],
            [Move::initiative(['ann' => 3], 'ann'), 'The game refuses the move "engine.initiative" now.'],
            [new Move('scene.end', 'ann'), 'The game refuses the move "scene.end" now.'],
            [new Move('scene.act'), 'The game refuses the move "scene.act" now.'],
            [new Move('scene.note', 'ann'), 'The game refuses the move "scene.note" now.'],
            [new Move('scene.dance', 'ann'), 'The game refuses the move "scene.dance" now.'],
            [Move::transition('end-encounter', 'gm'), 'The transition "end-encounter" cannot leave the phase "exploration".'],
            [new Move(Move::TRANSITION, 'gm'), 'A change of phase names its transition: {"transition": "…"}.'],
            [Move::endTurn('gm'), 'The phase "exploration" has no turns to end.'],
            [Move::delay('gm', 'ann'), 'A seat delays its own turn, in a phase by initiative, after a seat that acts later: {"after": "seat"}.'],
            [new Move('engine.pause', 'gm'), 'The engine has no move "engine.pause".'],
            [new Move(Move::INITIATIVE, 'gm', ['scores' => []]), 'The initiative gives scores by seat: {"scores": {"seat": 12}}.'],
            [new Move(Move::INITIATIVE, 'gm', ['scores' => 'high']), 'The initiative gives scores by seat: {"scores": {"seat": 12}}.'],
            [Move::initiative(['zed' => 3], 'gm'), 'The initiative of "zed" is not a whole number for a seat of the table.'],
            [new Move(Move::INITIATIVE, 'gm', ['scores' => ['ann' => 'high']]), 'The initiative of "ann" is not a whole number for a seat of the table.'],
        ] as [$move, $message]) {
            try {
                $engine->play($scene, $table, $move);
                self::fail(\sprintf('The move "%s" was accepted.', $move->type));
            } catch (IllegalMove $exception) {
                self::assertSame($message, $exception->getMessage());
            }
        }

        self::assertSame('encounter', $engine->play($scene, $table, Move::transition('start-encounter'))->state->phase);
        $open = $engine->start($scene, 'scene:2', ['gm', 'ann'], ['leader' => false], self::SEED);
        self::assertSame('encounter', $engine->play($scene, $open, Move::transition('start-encounter', 'ann'))->state->phase);
        $encounter = $engine->play($scene, $table, Move::transition('start-encounter', 'gm'));

        foreach ([Move::delay('gm', 'gm'), new Move(Move::DELAY, 'gm'), new Move(Move::DELAY, null, ['after' => 'ann'])] as $move) {
            try {
                $engine->play($scene, $encounter, $move);
                self::fail('The delay was accepted.');
            } catch (IllegalMove $exception) {
                self::assertStringStartsWith('A seat delays its own turn', $exception->getMessage());
            }
        }
    }

    public function testTheMachineOfTheSceneIsDrawn(): void
    {
        $machine = new RoleplayingScene()->definition()->machine;

        self::assertSame(['start-encounter'], $machine->from('exploration'));
        self::assertSame(['end-encounter', 'explore'], $machine->from('encounter'));
        self::assertTrue($machine->can('aftermath', 'explore'));
        self::assertFalse($machine->can('exploration', 'explore'));
        self::assertStringStartsWith("graph LR\n", $machine->mermaid());
        self::assertStringContainsString('start-encounter', $machine->mermaid());
        self::assertNotSame($machine->mermaid(), $machine->mermaid('encounter'));
    }

    private function play(Engine $engine, RoleplayingScene $scene, Table $table, Move ...$moves): Table
    {
        foreach ($moves as $move) {
            $table = $engine->play($scene, $table, $move);
        }

        return $table;
    }
}
