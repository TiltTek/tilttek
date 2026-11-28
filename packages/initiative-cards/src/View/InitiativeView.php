<?php

declare(strict_types=1);

namespace Tilttek\InitiativeCards\View;

/**
 * What one viewer may see of an encounter: the round, the slots in turn order — without the hidden combatants, unless
 * the viewer is the game master — the cards left to draw, whether it has ended, and the version it was read at.
 */
final readonly class InitiativeView
{
    /**
     * @param list<Slot> $slots
     */
    public function __construct(
        public string $encounter,
        public int $round,
        public array $slots,
        public int $drawPile,
        public bool $ended,
        public int $version,
    ) {
    }

    /**
     * The view as plain data, to send to the viewer.
     *
     * @return array{encounter: string, round: int, slots: list<array{combatant: string, face: ?string, acted: bool, defeated: bool, current: bool, hidden: bool}>, drawPile: int, ended: bool, version: int}
     */
    public function toArray(): array
    {
        return [
            'encounter' => $this->encounter,
            'round' => $this->round,
            'slots' => array_map(static fn (Slot $slot): array => $slot->toArray(), $this->slots),
            'drawPile' => $this->drawPile,
            'ended' => $this->ended,
            'version' => $this->version,
        ];
    }
}
