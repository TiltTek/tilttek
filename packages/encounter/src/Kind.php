<?php

declare(strict_types=1);

namespace Tilttek\Encounter;

/**
 * What a participant is to the encounter: a player's character, an adversary the game master runs against them, or an
 * ally who fights beside them.
 */
enum Kind: string
{
    case Character = 'character';
    case Adversary = 'adversary';
    case Ally = 'ally';
}
