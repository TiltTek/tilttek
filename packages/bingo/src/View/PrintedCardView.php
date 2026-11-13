<?php

declare(strict_types=1);

namespace Tilttek\Bingo\View;

use Tilttek\Bingo\Bingo;
use Tilttek\Bingo\BingoGame;
use Tilttek\Bingo\Card;

/**
 * What `templates/print.html.twig` shows of a printed card: its label and its rows of cells, none ticked.
 */
final readonly class PrintedCardView
{
    /**
     * @param list<list<CellView>> $rows
     */
    public function __construct(
        public string $game,
        public int $serial,
        public string $label,
        public array $rows,
    ) {
    }

    public static function of(Bingo $bingo, BingoGame $game, Card $card): self
    {
        $cells = [];

        foreach ($card->layout as $square) {
            $cells[] = new CellView($square, null === $square ? null : $game->pool->get($square)->label, false);
        }

        return new self($game->id, $card->serial, $bingo->label($game->id, $card->serial)->toString(), array_chunk($cells, $game->grid->size));
    }

    /**
     * The views of a batch, in its order.
     *
     * @param list<Card> $cards
     *
     * @return list<self>
     */
    public static function ofBatch(Bingo $bingo, BingoGame $game, array $cards): array
    {
        return array_map(static fn (Card $card): self => self::of($bingo, $game, $card), $cards);
    }
}
