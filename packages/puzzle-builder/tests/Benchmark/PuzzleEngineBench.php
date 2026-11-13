<?php

declare(strict_types=1);

namespace Tilttek\PuzzleBuilder\Tests\Benchmark;

use Phil\Resource\ResourceReference;
use Tilttek\PuzzleBuilder\Limiter\InMemoryGuessLimiter;
use Tilttek\PuzzleBuilder\Progress;
use Tilttek\PuzzleBuilder\PuzzleEngine;
use Tilttek\PuzzleBuilder\Scenario;

/**
 * What the puzzles cost on the largest scenario: 200 puzzles in a chain, read and checked from JSON, a guess on the
 * last puzzle once 199 are solved — the statuses of every puzzle are read again —, and the view sent to the browser.
 */
final readonly class PuzzleEngineBench
{
    private string $json;

    private Scenario $scenario;

    private PuzzleEngine $engine;

    private Progress $progress;

    private ResourceReference $team;

    public function __construct()
    {
        $puzzles = [];

        for ($index = 1; $index <= Scenario::MAX_PUZZLES; ++$index) {
            $puzzles[] = [
                'id' => 'p'.$index,
                'title' => 'Puzzle '.$index,
                'answers' => ['Answer number '.$index, 'Réponse '.$index],
                'partials' => ['number '.$index => 'Keep going'],
                'hints' => [['text' => 'First hint'], ['text' => 'Second hint']],
                'unlock' => 1 === $index ? [] : ['requires' => ['p'.($index - 1)]],
            ];
        }

        $this->json = json_encode(['id' => 'long', 'puzzles' => $puzzles], \JSON_THROW_ON_ERROR);
        $this->scenario = Scenario::fromJson($this->json);
        $this->engine = new PuzzleEngine(new InMemoryGuessLimiter(1_000_000, 60));
        $this->team = ResourceReference::of('team', '1');
        $progress = Progress::start();

        for ($index = 1; $index < Scenario::MAX_PUZZLES; ++$index) {
            $progress = $this->engine->skip($this->scenario, $progress, $this->team, 'p'.$index)->progress;
        }

        $this->progress = $progress;
    }

    public function benchReadAScenarioOf200Puzzles(): void
    {
        Scenario::fromJson($this->json);
    }

    public function benchGuessTheLastOf200Puzzles(): void
    {
        $this->engine->guess($this->scenario, $this->progress, $this->team, 'p200', 'answer number 200');
    }

    public function benchViewAGameOf200Puzzles(): void
    {
        $this->engine->view($this->scenario, $this->progress)->toArray();
    }
}
