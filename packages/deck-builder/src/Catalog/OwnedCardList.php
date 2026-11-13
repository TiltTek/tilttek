<?php

declare(strict_types=1);

namespace Tilttek\DeckBuilder\Catalog;

use Override;
use Tilttek\DeckBuilder\Contract\OwnedCards;
use Tilttek\DeckBuilder\Exception\InvalidDeck;
use Tilttek\DeckBuilder\Identifiers;

/**
 * A collection given as a list of card identifiers and copies, read once from the application's collection.
 */
final readonly class OwnedCardList implements OwnedCards
{
    /** @var array<array-key, int> */
    private array $copies;

    /**
     * @param array<array-key, int> $copies the identifiers of the cards owned, each with its copies, 0 or more
     *
     * @throws InvalidDeck when an identifier or a number of copies is refused
     */
    public function __construct(array $copies)
    {
        $checked = [];

        foreach ($copies as $card => $count) {
            $card = Identifiers::id((string) $card, 'card');

            if ($count < 0) {
                throw InvalidDeck::because(\sprintf('A collection owns 0 or more copies of the card "%s"; %d is not.', $card, $count));
            }

            $checked[$card] = $count;
        }

        $this->copies = $checked;
    }

    #[Override]
    public function copiesOf(string $card): int
    {
        return $this->copies[$card] ?? 0;
    }
}
