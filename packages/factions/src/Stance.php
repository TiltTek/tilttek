<?php

declare(strict_types=1);

namespace Tilttek\Factions;

/**
 * How two factions stand with each other, from the closest to the most opposed.
 */
enum Stance: string
{
    case Allied = 'allied';
    case Friendly = 'friendly';
    case Neutral = 'neutral';
    case Rival = 'rival';
    case Hostile = 'hostile';
    case War = 'war';
}
