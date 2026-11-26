<?php

declare(strict_types=1);

namespace Tilttek\MiniGames;

use Tilttek\MiniGames\Exception\InvalidMiniGame;

/**
 * The characters a shared result is written with, one per kind of cell: coloured squares, or plain characters that
 * every screen reader and every font shows.
 */
final readonly class CellSymbols
{
    /**
     * @throws InvalidMiniGame when a symbol is empty, longer than 16 bytes, holds a line break or is not UTF-8
     */
    public function __construct(
        public string $hit,
        public string $near,
        public string $miss,
        public string $empty,
    ) {
        foreach ([$hit, $near, $miss, $empty] as $symbol) {
            if (\strlen($symbol) > 16 || 1 !== preg_match('/^[^\r\n]+$/Du', $symbol)) {
                throw InvalidMiniGame::because('A symbol of a shared result is 1 to 16 bytes of UTF-8 on one line.');
            }
        }
    }

    /**
     * Green, yellow and black squares, and a white square for a cell not played.
     */
    public static function squares(): self
    {
        return new self("\u{1F7E9}", "\u{1F7E8}", "\u{2B1B}", "\u{2B1C}");
    }

    /**
     * `#`, `+`, `.` and `-`.
     */
    public static function plain(): self
    {
        return new self('#', '+', '.', '-');
    }

    public function of(Cell $cell): string
    {
        return match ($cell) {
            Cell::Hit => $this->hit,
            Cell::Near => $this->near,
            Cell::Miss => $this->miss,
            Cell::Empty => $this->empty,
        };
    }
}
