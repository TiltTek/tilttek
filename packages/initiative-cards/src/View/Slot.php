<?php

declare(strict_types=1);

namespace Tilttek\InitiativeCards\View;

/**
 * One combatant as a viewer sees it: its identifier, the face of its card or null, whether it has acted, is defeated
 * (faded), holds the turn, and is hidden from the players — which only the game master's view shows.
 */
final readonly class Slot
{
    public function __construct(
        public string $combatant,
        public ?string $face,
        public bool $acted,
        public bool $defeated,
        public bool $current,
        public bool $hidden,
    ) {
    }

    /**
     * @return array{combatant: string, face: ?string, acted: bool, defeated: bool, current: bool, hidden: bool}
     */
    public function toArray(): array
    {
        return [
            'combatant' => $this->combatant,
            'face' => $this->face,
            'acted' => $this->acted,
            'defeated' => $this->defeated,
            'current' => $this->current,
            'hidden' => $this->hidden,
        ];
    }
}
