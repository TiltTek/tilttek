<?php

declare(strict_types=1);

namespace Tilttek\PuzzleBuilder;

use Tilttek\PuzzleBuilder\Exception\InvalidScenario;

/**
 * When a puzzle opens: once enough of the puzzles it requires are solved, once enough puzzles of the scenario are
 * solved, both, or only when the host opens it. A puzzle with no condition is open from the start.
 */
final readonly class Unlock
{
    public const int MAX_REQUIRES = 50;

    /** @var list<string> */
    public array $requires;

    /** How many of the puzzles required must be solved: all of them unless fewer are asked. */
    public int $minimum;

    /**
     * @param list<string> $requires    the puzzles that open this one, 50 at most
     * @param ?int         $minimum     how many of them must be solved, 1 to their number; null for all
     * @param int          $afterSolves how many puzzles of the scenario, any of them, must be solved
     * @param bool         $manual      opened by the host only, whatever is solved: a clue handed out in the second act
     *
     * @throws InvalidScenario when a puzzle is required twice, or a count is out of bounds
     */
    public function __construct(
        array $requires = [],
        ?int $minimum = null,
        public int $afterSolves = 0,
        public bool $manual = false,
    ) {
        if (\count($requires) > self::MAX_REQUIRES) {
            throw InvalidScenario::because(\sprintf('A puzzle requires %d others at most.', self::MAX_REQUIRES));
        }

        if (\count(array_unique($requires)) !== \count($requires)) {
            throw InvalidScenario::because('A puzzle requires each other puzzle once.');
        }

        $minimum ??= \count($requires);

        if ($minimum < ([] === $requires ? 0 : 1) || $minimum > \count($requires)) {
            throw InvalidScenario::because(\sprintf('The puzzles to solve among those required are 1 to %d.', \count($requires)));
        }

        if ($afterSolves < 0) {
            throw InvalidScenario::because('The puzzles to solve first are 0 or more.');
        }

        if ($manual && ([] !== $requires || 0 !== $afterSolves)) {
            throw InvalidScenario::because('A puzzle the host opens has no other condition.');
        }

        $this->requires = $requires;
        $this->minimum = $minimum;
    }

    public static function open(): self
    {
        return new self();
    }

    /**
     * @param list<string> $requires
     */
    public static function after(array $requires, ?int $minimum = null): self
    {
        return new self($requires, $minimum);
    }

    public static function afterSolves(int $count): self
    {
        return new self(afterSolves: $count);
    }

    public static function manual(): self
    {
        return new self(manual: true);
    }

    /**
     * Whether the puzzle opens once these puzzles are solved; a puzzle the host opens never does by itself.
     *
     * @param array<string, mixed> $solved the puzzles solved, by their identifier
     */
    public function opensWith(array $solved): bool
    {
        if ($this->manual || \count($solved) < $this->afterSolves) {
            return false;
        }

        $met = 0;

        foreach ($this->requires as $puzzle) {
            if (\array_key_exists($puzzle, $solved)) {
                ++$met;
            }
        }

        return $met >= $this->minimum;
    }
}
