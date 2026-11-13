<?php

declare(strict_types=1);

namespace Tilttek\GameEngine\Tests\Benchmark;

use Tilttek\GameEngine\Engine;
use Tilttek\GameEngine\GameLog;
use Tilttek\GameEngine\Move;
use Tilttek\GameEngine\Scene\RoleplayingScene;
use Tilttek\GameEngine\Table;

/**
 * What a table costs: one move played on a cached state — every move of every table —, and the log replayed when the
 * cache lags — after a deploy, or for a table read again —, for a scene of 100 and of 1 000 moves.
 */
final class ReplayBench
{
    private readonly Engine $engine;

    private readonly RoleplayingScene $scene;

    /** @var array<int, Table> */
    private array $tables = [];

    public function __construct()
    {
        $this->engine = new Engine();
        $this->scene = new RoleplayingScene();

        foreach ([100, 1000] as $moves) {
            $this->tables[$moves] = $this->table($moves);
        }
    }

    public function benchPlayAMove(): void
    {
        $table = $this->tables[100];
        $this->engine->play($this->scene, $table, new Move('scene.note', 'ann', ['text' => 'A note.']));
    }

    /**
     * @Revs(20)
     *
     * @Iterations(3)
     */
    public function benchReplay100Moves(): void
    {
        $this->engine->replay($this->scene, $this->tables[100]->log);
    }

    /**
     * @Revs(5)
     *
     * @Iterations(3)
     */
    public function benchReplay1000Moves(): void
    {
        $this->engine->replay($this->scene, $this->tables[1000]->log);
    }

    public function benchReadALogBack(): void
    {
        GameLog::fromArray($this->tables[100]->log->toArray());
    }

    private function table(int $moves): Table
    {
        $table = $this->engine->start($this->scene, 'bench:'.$moves, ['gm', 'ann', 'bob', 'cy'], [], str_repeat('ab', 32));
        $table = $this->engine->play($this->scene, $table, Move::initiative(['ann' => 15, 'bob' => 12, 'cy' => 9], 'gm'));
        $table = $this->engine->play($this->scene, $table, Move::transition('start-encounter', 'gm'));

        while ($table->log->sequence() < $moves) {
            $seat = $table->state->turn->toAct()[0];
            $table = $this->engine->play($this->scene, $table, new Move('scene.act', $seat, ['summary' => 'Strikes.']));
            $table = $this->engine->play($this->scene, $table, Move::endTurn($seat));
        }

        return $table;
    }
}
