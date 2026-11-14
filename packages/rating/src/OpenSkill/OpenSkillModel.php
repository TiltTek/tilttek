<?php

declare(strict_types=1);

namespace Tilttek\Rating\OpenSkill;

/**
 * The models of Weng and Lin (2011) that `OpenSkillSystem` applies.
 */
enum OpenSkillModel: string
{
    /**
     * The Plackett-Luce model: the order of finish as a sequence of choices, each side chosen first among those left
     * in proportion to the exponential of its skill. One pass over the sides for any number of them.
     */
    case PlackettLuce = 'openskill-pl';

    /**
     * The Thurstone-Mosteller model with full pairing: every pair of sides compared as a duel of two normal
     * performances, with a draw margin.
     */
    case ThurstoneMosteller = 'openskill-tm';
}
