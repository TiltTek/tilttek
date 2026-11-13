<?php

declare(strict_types=1);

namespace Tilttek\Bingo\View;

use Tilttek\Bingo\Bingo;
use Tilttek\Bingo\BingoGame;
use Tilttek\Bingo\Card;

/**
 * What `templates/card.html.twig` shows of a card: its rows of cells, ticked or not, and whether it won, which the
 * Stimulus controller of the package says to the page.
 */
final readonly class CardView
{
    /**
     * @param list<list<CellView>> $rows
     */
    public function __construct(
        public string $game,
        public int $serial,
        public array $rows,
        public ?string $pattern,
    ) {
    }

    public static function of(Bingo $bingo, BingoGame $game, Card $card): self
    {
        $ticked = $bingo->ticked($game, $card);
        $cells = [];

        foreach ($card->layout as $cell => $square) {
            $cells[] = new CellView($square, null === $square ? null : $game->pool->get($square)->label, isset($ticked[$cell]));
        }

        return new self($game->id, $card->serial, array_chunk($cells, $game->grid->size), $card->pattern());
    }

    public function hasWon(): bool
    {
        return null !== $this->pattern;
    }
}
