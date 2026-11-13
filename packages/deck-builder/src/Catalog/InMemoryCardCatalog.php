<?php

declare(strict_types=1);

namespace Tilttek\DeckBuilder\Catalog;

use Override;
use Tilttek\DeckBuilder\Card;
use Tilttek\DeckBuilder\Contract\CardCatalog;

/**
 * Cards held in memory: cards declared in the application's configuration, tests and examples.
 */
final readonly class InMemoryCardCatalog implements CardCatalog
{
    /** @var array<string, Card> */
    private array $cards;

    public function __construct(Card ...$cards)
    {
        $byId = [];

        foreach ($cards as $card) {
            $byId[$card->id] = $card;
        }

        $this->cards = $byId;
    }

    #[Override]
    public function find(string $id): ?Card
    {
        return $this->cards[$id] ?? null;
    }
}
