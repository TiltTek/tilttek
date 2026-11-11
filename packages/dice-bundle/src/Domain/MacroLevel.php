<?php

declare(strict_types=1);

namespace Tilttek\DiceBundle\Domain;

/**
 * Where a macro is kept: for a whole site, for a table, or for one member.
 */
enum MacroLevel: string
{
    case Site = 'site';
    case Table = 'table';
    case Member = 'member';
}
