<?php

declare(strict_types=1);

namespace Tilttek\PuzzleBuilder;

use DateTimeImmutable;
use Tilttek\PuzzleBuilder\Exception\InvalidProgress;
use Tilttek\PuzzleBuilder\Record\HintTaken;
use Tilttek\PuzzleBuilder\Record\Solved;

/**
 * Where one team stands in a scenario: the puzzles solved, those the host opened, the hints revealed and how many
 * guesses each puzzle took. The library keeps none of it: each move gives a new `Progress`, which the application
 * stores as `toArray()` writes it and gives back on the next move.
 */
final readonly class Progress
{
    public const int MAX_GUESSES = 1_000_000;

    private const array FIELDS = ['solved', 'opened', 'hints', 'guesses'];

    /**
     * @param array<string, Solved>            $solved
     * @param array<string, DateTimeImmutable> $opened
     * @param list<HintTaken>                  $hints
     * @param array<string, int>               $guesses
     */
    private function __construct(
        private array $solved = [],
        private array $opened = [],
        private array $hints = [],
        private array $guesses = [],
    ) {
    }

    public static function start(): self
    {
        return new self();
    }

    /**
     * Reads back what `toArray()` wrote, checked against the scenario.
     *
     * @param array<array-key, mixed> $values
     *
     * @throws InvalidProgress when the values are not the progress of a game of this scenario
     */
    public static function fromArray(Scenario $scenario, array $values): self
    {
        foreach (array_keys($values) as $field) {
            if (!\in_array($field, self::FIELDS, true)) {
                throw InvalidProgress::because(\sprintf('The field "%s" is not one of a progress.', mb_substr((string) $field, 0, 64)));
            }
        }

        $progress = new self();

        foreach (self::map($values, 'solved') as $puzzle => $solved) {
            if (!\is_array($solved) || !\is_bool($solved['skipped'] ?? null) || 2 !== \count($solved)) {
                throw InvalidProgress::because('A puzzle solved holds its time and whether it was skipped.');
            }

            $progress = $progress->withSolved(new Solved(self::puzzle($scenario, $puzzle)->id, Instant::read($solved['at'] ?? null), $solved['skipped']));
        }

        foreach (self::map($values, 'opened') as $puzzle => $at) {
            if (!self::puzzle($scenario, $puzzle)->unlock->manual) {
                throw InvalidProgress::because('A progress names the puzzles the host opens only as opened.');
            }

            $progress = $progress->withOpened(self::puzzle($scenario, $puzzle)->id, Instant::read($at));
        }

        $hints = $values['hints'] ?? [];

        if (!\is_array($hints) || !array_is_list($hints)) {
            throw InvalidProgress::because('The hints revealed are a list.');
        }

        $tiers = [];

        foreach ($hints as $hint) {
            if (!\is_array($hint) || 4 !== \count($hint) || !\is_int($hint['tier'] ?? null) || !\is_bool($hint['pushed'] ?? null)) {
                throw InvalidProgress::because('A hint revealed holds its puzzle, its tier, its time and whether it was pushed.');
            }

            $puzzle = self::puzzle($scenario, $hint['puzzle'] ?? null);

            $tiers[$puzzle->id] = ($tiers[$puzzle->id] ?? 0) + 1;

            if ($hint['tier'] !== $tiers[$puzzle->id] || $hint['tier'] > \count($puzzle->hints)) {
                throw InvalidProgress::because(\sprintf('The hints of the puzzle "%s" are revealed tier after tier, up to its last.', $puzzle->id));
            }

            $progress = $progress->withHint(new HintTaken($puzzle->id, $hint['tier'], Instant::read($hint['at'] ?? null), $hint['pushed']));
        }

        foreach (self::map($values, 'guesses') as $puzzle => $count) {
            if (!\is_int($count) || $count < 1 || $count > self::MAX_GUESSES) {
                throw InvalidProgress::because(\sprintf('The guesses of a puzzle are 1 to %d.', self::MAX_GUESSES));
            }

            $progress = new self($progress->solved, $progress->opened, $progress->hints, [...$progress->guesses, self::puzzle($scenario, $puzzle)->id => $count]);
        }

        $progress->followsTheScenario($scenario);

        return $progress;
    }

    public function isSolved(string $puzzle): bool
    {
        return isset($this->solved[$puzzle]);
    }

    /**
     * @return array<string, Solved> by puzzle, in the order they were solved
     */
    public function solved(): array
    {
        return $this->solved;
    }

    /**
     * Whether the host opened this puzzle.
     */
    public function isOpened(string $puzzle): bool
    {
        return isset($this->opened[$puzzle]);
    }

    /**
     * @return list<HintTaken> in the order they were revealed
     */
    public function hints(): array
    {
        return $this->hints;
    }

    /**
     * @return list<HintTaken>
     */
    public function hintsOf(string $puzzle): array
    {
        return array_values(array_filter($this->hints, static fn (HintTaken $hint): bool => $hint->puzzle === $puzzle));
    }

    /**
     * How many guesses of this puzzle were checked; a guess refused or throttled is not counted.
     */
    public function guesses(string $puzzle): int
    {
        return $this->guesses[$puzzle] ?? 0;
    }

    /**
     * @internal the engine's move
     */
    public function withSolved(Solved $solved): self
    {
        return new self([...$this->solved, $solved->puzzle => $solved], $this->opened, $this->hints, $this->guesses);
    }

    /**
     * @internal the engine's move
     */
    public function withOpened(string $puzzle, DateTimeImmutable $at): self
    {
        return new self($this->solved, [...$this->opened, $puzzle => Instant::utc($at)], $this->hints, $this->guesses);
    }

    /**
     * @internal the engine's move
     */
    public function withHint(HintTaken $hint): self
    {
        return new self($this->solved, $this->opened, [...$this->hints, $hint], $this->guesses);
    }

    /**
     * @internal the engine's move
     */
    public function withGuess(string $puzzle): self
    {
        return new self($this->solved, $this->opened, $this->hints, [...$this->guesses, $puzzle => min(self::MAX_GUESSES, $this->guesses($puzzle) + 1)]);
    }

    /**
     * The progress as plain values, to store; `fromArray()` reads them back.
     *
     * @return array{solved: array<string, array{at: string, skipped: bool}>, opened: array<string, string>, hints: list<array{puzzle: string, tier: int, at: string, pushed: bool}>, guesses: array<string, int>}
     */
    public function toArray(): array
    {
        return [
            'solved' => array_map(static fn (Solved $solved): array => ['at' => Instant::write($solved->at), 'skipped' => $solved->skipped], $this->solved),
            'opened' => array_map(Instant::write(...), $this->opened),
            'hints' => array_map(static fn (HintTaken $hint): array => ['puzzle' => $hint->puzzle, 'tier' => $hint->tier, 'at' => Instant::write($hint->at), 'pushed' => $hint->pushed], $this->hints),
            'guesses' => $this->guesses,
        ];
    }

    /**
     * Whether the moves could have been played: each puzzle solved, in the order of its time, was open by then — the
     * host had opened it, or the puzzles solved before it open it —, and so was each puzzle of a hint at its time and
     * each puzzle guessed. A progress written by the engine always is; one edited by hand or forged may not be.
     *
     * @throws InvalidProgress when a move could not have been played
     */
    private function followsTheScenario(Scenario $scenario): void
    {
        $before = [];
        $last = null;

        foreach ($this->solved as $puzzle => $solved) {
            if ($last instanceof DateTimeImmutable && $solved->at < $last) {
                throw InvalidProgress::because('The puzzles solved are in the order of their times.');
            }

            if (!$this->wasOpen($scenario, $puzzle, $solved->at, $before)) {
                throw InvalidProgress::because(\sprintf('The puzzle "%s" is solved while it was locked.', $puzzle));
            }

            $before[$puzzle] = $solved;
            $last = $solved->at;
        }

        foreach ($this->hints as $hint) {
            $solvedThen = array_filter($this->solved, static fn (Solved $solved): bool => $solved->at <= $hint->at);

            if (!$this->wasOpen($scenario, $hint->puzzle, $hint->at, $solvedThen)) {
                throw InvalidProgress::because(\sprintf('A hint of the puzzle "%s" is revealed while it was locked.', $hint->puzzle));
            }
        }

        foreach (array_keys($this->guesses) as $puzzle) {
            if (!$this->wasOpen($scenario, $puzzle, null, $this->solved)) {
                throw InvalidProgress::because(\sprintf('The puzzle "%s" is guessed while it is locked.', $puzzle));
            }
        }
    }

    /**
     * Whether a puzzle was open at a time, or now when it is null, with these puzzles solved.
     *
     * @param array<string, Solved> $solved
     */
    private function wasOpen(Scenario $scenario, string $puzzle, ?DateTimeImmutable $at, array $solved): bool
    {
        $opened = $this->opened[$puzzle] ?? null;

        if ($opened instanceof DateTimeImmutable && (!$at instanceof DateTimeImmutable || $opened <= $at)) {
            return true;
        }

        return $scenario->puzzle($puzzle)->unlock->opensWith($solved);
    }

    /**
     * @param array<array-key, mixed> $values
     *
     * @return array<array-key, mixed>
     */
    private static function map(array $values, string $field): array
    {
        $map = $values[$field] ?? [];

        if (!\is_array($map) || (array_is_list($map) && [] !== $map)) {
            throw InvalidProgress::because(\sprintf('The field "%s" of a progress is an object by puzzle.', $field));
        }

        return $map;
    }

    private static function puzzle(Scenario $scenario, mixed $puzzle): Puzzle
    {
        if (!\is_string($puzzle) || !$scenario->has($puzzle)) {
            throw InvalidProgress::because('A progress names the puzzles of its scenario only.');
        }

        return $scenario->puzzle($puzzle);
    }
}
