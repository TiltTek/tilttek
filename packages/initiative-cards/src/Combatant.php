<?php

declare(strict_types=1);

namespace Tilttek\InitiativeCards;

use Tilttek\InitiativeCards\Exception\InvalidEncounter;
use Tilttek\InitiativeCards\Internal\Identifier;

/**
 * One combatant of an encounter: who it is, the member who ends its turns — a player for their character, the game
 * master for the others — whether the players see it, whether it is defeated, how many cards it draws to keep the best,
 * and, in a round, the card it holds and whether it has acted.
 */
final readonly class Combatant
{
    /** The most cards a combatant draws to keep the best. */
    public const int MAX_DRAWS = 3;

    /**
     * @param string  $id         the combatant's identifier, unique in the encounter
     * @param string  $controller the member who ends its turns, besides the game master
     * @param bool    $hidden     whether the players do not see it: only the game master does
     * @param int     $draws      1 to 3 cards drawn in each round, the best one kept
     * @param bool    $defeated   whether it is defeated: faded, its turns skipped
     * @param ?string $card       the identifier of the card it holds in the round, or null
     * @param bool    $acted      whether it has acted in the round
     *
     * @throws InvalidEncounter when an identifier is not one, or the draws are out of their bounds
     */
    public function __construct(
        public string $id,
        public string $controller,
        public bool $hidden = false,
        public int $draws = 1,
        public bool $defeated = false,
        public ?string $card = null,
        public bool $acted = false,
    ) {
        Identifier::check($id, 'a combatant');
        Identifier::check($controller, 'a member');
        if ($draws < 1 || $draws > self::MAX_DRAWS) {
            throw InvalidEncounter::because(\sprintf('A combatant draws 1 to %d cards.', self::MAX_DRAWS));
        }
    }

    /**
     * Whether its turn may come in the round: it holds a card, it has not acted, and it is not defeated.
     */
    public function isPending(): bool
    {
        return null !== $this->card && !$this->acted && !$this->defeated;
    }

    public function withCard(?string $card): self
    {
        return new self($this->id, $this->controller, $this->hidden, $this->draws, $this->defeated, $card, false);
    }

    public function withActed(bool $acted): self
    {
        return new self($this->id, $this->controller, $this->hidden, $this->draws, $this->defeated, $this->card, $acted);
    }

    public function withDefeated(bool $defeated): self
    {
        return new self($this->id, $this->controller, $this->hidden, $this->draws, $defeated, $this->card, $this->acted);
    }

    public function withHidden(bool $hidden): self
    {
        return new self($this->id, $this->controller, $hidden, $this->draws, $this->defeated, $this->card, $this->acted);
    }
}
