<?php

declare(strict_types=1);

namespace Tilttek\MiniGames;

use Tilttek\MiniGames\Exception\InvalidMiniGame;

/**
 * A result to share that gives nothing away: rows of cells that say how each guess went and never what it was, and a
 * summary such as `4/10`. Written as text, it pastes anywhere.
 */
final readonly class ShareGrid
{
    public const int MAX_ROWS = 32;

    public const int MAX_COLUMNS = 16;

    /**
     * @param list<list<Cell>> $rows    up to `MAX_ROWS` rows of up to `MAX_COLUMNS` cells
     * @param string           $summary the score, `4/10`, up to 32 printable ASCII characters
     *
     * @throws InvalidMiniGame
     */
    public function __construct(
        public array $rows,
        public string $summary,
    ) {
        if (\count($rows) > self::MAX_ROWS) {
            throw InvalidMiniGame::because(\sprintf('A shared result has at most %d rows.', self::MAX_ROWS));
        }

        foreach ($rows as $row) {
            if (\count($row) > self::MAX_COLUMNS) {
                throw InvalidMiniGame::because(\sprintf('A row of a shared result has at most %d cells.', self::MAX_COLUMNS));
            }
        }

        if (1 !== preg_match('/^[\x20-\x7E]{0,32}$/D', $summary)) {
            throw InvalidMiniGame::because('The summary of a shared result is up to 32 printable ASCII characters.');
        }
    }

    /**
     * The result as text: a title line — the title, then the summary —, a blank line, and one line per row.
     *
     * @param string       $title   the game and its number, `Bulls and Cows 12`, which the application writes in its
     *                              language; its runs of spaces and line breaks become one space
     * @param ?CellSymbols $symbols the coloured squares when null
     */
    public function toText(string $title, ?CellSymbols $symbols = null): string
    {
        $symbols ??= CellSymbols::squares();
        $lines = [trim(trim(preg_replace('/\s+/u', ' ', $title) ?? '').' '.$this->summary)];

        if ([] !== $this->rows) {
            $lines[] = '';
        }

        foreach ($this->rows as $row) {
            $lines[] = implode('', array_map($symbols->of(...), $row));
        }

        return implode("\n", $lines);
    }
}
