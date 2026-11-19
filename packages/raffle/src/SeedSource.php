<?php

declare(strict_types=1);

namespace Tilttek\Raffle;

/**
 * Where the randomness of a draw comes from.
 */
enum SeedSource: string
{
    /** A future round of a public randomness beacon, fixed when the entries close. */
    case Beacon = 'beacon';

    /** A seed drawn by the organiser when the raffle opens, its SHA-256 published then and the seed revealed at the draw. */
    case CommitReveal = 'commit-reveal';
}
