<?php

declare(strict_types=1);

namespace Tilttek\Encounter\Initiative;

use Tilttek\Encounter\Kind;

/**
 * One combatant handed to an initiative tracker: one copy of a participant, with what a tracker needs to order its turns
 * and to show it — never a name, which the application reads from its record.
 */
final readonly class RosterEntry
{
    /**
     * @param string  $combatant       the combatant's identifier, unique in the encounter: the participant's, or
     *                                 `goblins.2` for a copy
     * @param string  $participant     the identifier of the participant it is a copy of
     * @param string  $controller      the member who ends its turns
     * @param bool    $hidden          whether the players do not see it
     * @param ?int    $initiativeBonus what it adds to its initiative, or null
     * @param ?string $ref             the identifier of the application's record, or null
     */
    public function __construct(
        public string $combatant,
        public string $participant,
        public Kind $kind,
        public string $controller,
        public bool $hidden,
        public ?int $initiativeBonus,
        public ?string $ref,
    ) {
    }

    /**
     * @return array{combatant: string, participant: string, kind: string, controller: string, hidden: bool, initiativeBonus: ?int, ref: ?string}
     */
    public function toArray(): array
    {
        return [
            'combatant' => $this->combatant,
            'participant' => $this->participant,
            'kind' => $this->kind->value,
            'controller' => $this->controller,
            'hidden' => $this->hidden,
            'initiativeBonus' => $this->initiativeBonus,
            'ref' => $this->ref,
        ];
    }
}
