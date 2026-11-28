<?php

declare(strict_types=1);

namespace Tilttek\Encounter\Initiative;

use Tilttek\Encounter\Encounter;

/**
 * What an initiative tracker receives to run an encounter: its identifier, its game master, its game system, and every
 * combatant in the order the game master set — the copies of a participant one after the other. The tracker decides
 * the order of the turns: by cards, by rolls, or as given.
 */
final readonly class InitiativeRoster
{
    /**
     * @param list<RosterEntry> $entries in the order the game master set
     */
    public function __construct(public string $encounter, public string $gameMaster, public string $system, public array $entries)
    {
    }

    /**
     * The roster of an encounter, every copy of a participant made one combatant.
     */
    public static function of(Encounter $encounter): self
    {
        $entries = [];
        foreach ($encounter->participants as $participant) {
            foreach ($participant->combatants() as $combatant) {
                $entries[] = new RosterEntry(
                    $combatant,
                    $participant->id,
                    $participant->kind,
                    $participant->controller,
                    $participant->hidden,
                    $participant->initiativeBonus,
                    $participant->ref,
                );
            }
        }

        return new self($encounter->id, $encounter->gameMaster, $encounter->system, $entries);
    }

    /**
     * @return array{encounter: string, gameMaster: string, system: string, entries: list<array<string, mixed>>}
     */
    public function toArray(): array
    {
        return [
            'encounter' => $this->encounter,
            'gameMaster' => $this->gameMaster,
            'system' => $this->system,
            'entries' => array_map(static fn (RosterEntry $entry): array => $entry->toArray(), $this->entries),
        ];
    }
}
