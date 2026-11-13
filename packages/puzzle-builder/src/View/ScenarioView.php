<?php

declare(strict_types=1);

namespace Tilttek\PuzzleBuilder\View;

/**
 * What the players see of their game: each puzzle in the order of the scenario, and whether the game is over. It is
 * made to be sent to a browser as `toArray()` writes it, and holds nothing that checks an answer.
 */
final readonly class ScenarioView
{
    /**
     * @param list<PuzzleView> $puzzles
     */
    public function __construct(
        public string $scenario,
        public array $puzzles,
        public bool $completed,
    ) {
    }

    /**
     * @return array{scenario: string, completed: bool, puzzles: list<array{id: string, status: string, title: ?string, hints: list<string>, hints_left: int, guesses: int, solved_at: ?string, skipped: bool}>}
     */
    public function toArray(): array
    {
        return [
            'scenario' => $this->scenario,
            'completed' => $this->completed,
            'puzzles' => array_map(static fn (PuzzleView $puzzle): array => $puzzle->toArray(), $this->puzzles),
        ];
    }
}
