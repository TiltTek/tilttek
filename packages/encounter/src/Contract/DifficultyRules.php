<?php

declare(strict_types=1);

namespace Tilttek\Encounter\Contract;

use Tilttek\Encounter\Difficulty\Assessment;
use Tilttek\Encounter\Exception\EncounterRefused;
use Tilttek\Encounter\Exception\InvalidEncounter;
use Tilttek\Encounter\Participant;

/**
 * The rules of difficulty one game system declares: how much an encounter's adversaries cost, and from which cost it
 * is of each band of difficulty for its characters. An implementation keeps no state between calls.
 */
interface DifficultyRules
{
    /**
     * The key of the game system the rules belong to, `srd-5.2`.
     */
    public function system(): string;

    /**
     * @param list<Participant> $participants the participants of the encounter, in order
     *
     * @throws InvalidEncounter when a participant has a rating the rules do not know
     * @throws EncounterRefused when the rules cannot assess these participants: no character, an adversary beyond
     *                          what they rate
     */
    public function assess(array $participants): Assessment;
}
