<?php

declare(strict_types=1);

namespace Tilttek\CardDeck\Gesture;

use Tilttek\CardDeck\Deck;
use Tilttek\CardDeck\Exception\InvalidDefinition;
use Tilttek\CardDeck\PlayerId;
use Tilttek\CardDeck\ZoneId;

/**
 * Deals cards from the top of a zone, the draw pile unless another is named, one at a time around the players in the
 * order given, until each holds `count` more: face down, each seen by its player alone, or face up. When the zone holds
 * too few cards, nothing is dealt.
 */
final readonly class Deal implements Gesture
{
    public const int MAX_PLAYERS = 64;

    /** @var non-empty-list<PlayerId> */
    public array $players;

    public ZoneId $from;

    /**
     * @param list<PlayerId> $players
     *
     * @throws InvalidDefinition when the players are none, too many or twice the same, or the count is out of bounds
     */
    public function __construct(
        array $players,
        public int $count,
        public bool $faceUp = false,
        ?ZoneId $from = null,
    ) {
        $values = array_map(static fn (PlayerId $player): string => $player->value, $players);

        if ([] === $players || \count($players) > self::MAX_PLAYERS || \count(array_unique($values)) !== \count($values)) {
            throw InvalidDefinition::because(\sprintf('A deal goes to 1 to %d players, each once.', self::MAX_PLAYERS));
        }

        if ($count < 1 || $count > Deck::MAX_CARDS) {
            throw InvalidDefinition::because(\sprintf('A deal gives 1 to %d cards to each player.', Deck::MAX_CARDS));
        }

        $this->players = $players;
        $this->from = $from ?? ZoneId::drawPile();
    }

    public function toArray(): array
    {
        return ['gesture' => 'deal', 'players' => array_map(static fn (PlayerId $player): string => $player->value, $this->players), 'count' => $this->count, 'face_up' => $this->faceUp, 'from' => $this->from->toString()];
    }
}
