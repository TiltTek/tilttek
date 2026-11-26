<?php

declare(strict_types=1);

namespace Tilttek\Tournament\Contract;

/**
 * Where the strength of participants comes from, to order them before the first round: the seeds of a bracket, the
 * numbers of a pool, the order of a Swiss stage. `RatingSeeding` reads the ratings of `tilttek/rating`; `FixedSeeding`
 * takes the strengths given.
 */
interface SeedingSource
{
    /**
     * The strength of each participant known to the source, the strongest highest; a participant unknown is left out
     * and placed after the others.
     *
     * @param list<string> $participants
     *
     * @return array<string, int>
     */
    public function strengths(array $participants): array;
}
