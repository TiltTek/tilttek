<?php

declare(strict_types=1);

namespace Tilttek\Bingo;

use Tilttek\Bingo\Exception\InvalidBingo;

/**
 * The squares a game draws its cards from, each identifier once, at most `MOST_SQUARES`.
 */
final readonly class SquarePool
{
    public const int MOST_SQUARES = 500;

    /** @var non-empty-list<Square> */
    public array $squares;

    /** @var array<string, Square> */
    private array $byId;

    /**
     * @param list<Square> $squares
     */
    public function __construct(array $squares)
    {
        if ([] === $squares || \count($squares) > self::MOST_SQUARES) {
            throw InvalidBingo::because(\sprintf('A pool holds 1 to %d squares; this one holds %d.', self::MOST_SQUARES, \count($squares)));
        }

        $byId = [];

        foreach ($squares as $square) {
            if (isset($byId[$square->id])) {
                throw InvalidBingo::because(\sprintf('Two squares of the pool are %s.', $square->id));
            }

            $byId[$square->id] = $square;
        }

        $this->squares = $squares;
        $this->byId = $byId;
    }

    public function count(): int
    {
        return \count($this->squares);
    }

    public function has(string $square): bool
    {
        return isset($this->byId[$square]);
    }

    public function get(string $square): Square
    {
        return $this->byId[$square] ?? throw InvalidBingo::because(\sprintf('The pool has no square %s.', $square));
    }
}
