<?php

declare(strict_types=1);

namespace Tilttek\GameEngine;

use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer;
use Tilttek\GameEngine\Exception\InvalidGame;

/**
 * The chance a game draws from during one step of a table — its setup, or one move —: a sequence seeded by the table's
 * seed and the step's place in the log, so that replaying the log draws the same values. It notes whether it was drawn
 * from, which the log keeps.
 */
final class Randomness
{
    private ?Randomizer $randomizer = null;

    private bool $drawn = false;

    /**
     * @param string $seed     the table's seed, 64 hexadecimal digits
     * @param int    $sequence the step's place in the log: 0 for the setup, from 1 for the moves
     */
    public function __construct(private readonly string $seed, private readonly int $sequence)
    {
    }

    /**
     * A whole number from `$minimum` to `$maximum`, both included.
     */
    public function int(int $minimum, int $maximum): int
    {
        return $this->randomizer()->getInt($minimum, $maximum);
    }

    /**
     * One element of a list.
     *
     * @template T
     *
     * @param non-empty-list<T> $items
     *
     * @return T
     */
    public function pick(array $items): mixed
    {
        return $items[$this->randomizer()->getInt(0, \count($items) - 1)];
    }

    /**
     * A list in an order drawn at random.
     *
     * @template T
     *
     * @param list<T> $items
     *
     * @return list<T>
     */
    public function shuffle(array $items): array
    {
        return $this->randomizer()->shuffleArray($items);
    }

    /**
     * The randomizer itself, for a library that takes one — `tilttek/dice` rolls with it.
     */
    public function randomizer(): Randomizer
    {
        $this->drawn = true;

        return $this->randomizer ??= new Randomizer(new Xoshiro256StarStar(hash('sha256', self::check($this->seed).':'.$this->sequence, true)));
    }

    /**
     * Whether the step drew at random.
     */
    public function drawn(): bool
    {
        return $this->drawn;
    }

    /**
     * A new seed of 64 hexadecimal digits, from the system's secure source.
     */
    public static function seed(): string
    {
        return hash('sha256', random_bytes(32));
    }

    /**
     * The commitment to a seed: the SHA-256 digest of its 64 hexadecimal digits, in 64 hexadecimal digits. It tells
     * nothing of the draws to come, and only that seed gives it.
     *
     * @throws InvalidGame when the seed is not 64 lowercase hexadecimal digits
     */
    public static function commitment(string $seed): string
    {
        return hash('sha256', self::check($seed));
    }

    /**
     * @throws InvalidGame
     */
    public static function check(string $seed): string
    {
        if (1 !== preg_match('/^[0-9a-f]{64}$/D', $seed)) {
            throw InvalidGame::because('A seed is 64 lowercase hexadecimal digits.');
        }

        return $seed;
    }
}
