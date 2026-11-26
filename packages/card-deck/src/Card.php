<?php

declare(strict_types=1);

namespace Tilttek\CardDeck;

use Tilttek\CardDeck\Exception\InvalidDefinition;

/**
 * A card: its identity, the faces it can show — one for most cards, more for a card that turns or transforms — and its
 * back, which anyone sees when it lies face down.
 */
final readonly class Card
{
    public const int MAX_FACES = 16;

    /** @var non-empty-list<Face> */
    public array $faces;

    /**
     * @param list<Face> $faces the faces, the first one shown until a gesture turns another
     *
     * @throws InvalidDefinition when the card has no face or more than `MAX_FACES`
     */
    public function __construct(
        public CardId $id,
        array $faces,
        public Face $back,
    ) {
        if ([] === $faces || \count($faces) > self::MAX_FACES) {
            throw InvalidDefinition::because(\sprintf('A card has 1 to %d faces.', self::MAX_FACES));
        }

        $this->faces = $faces;
    }

    /**
     * A card of one face.
     */
    public static function of(string $id, string $face, string $back = 'back'): self
    {
        return new self(new CardId($id), [new Face($face)], new Face($back));
    }
}
