<?php

declare(strict_types=1);

namespace Tilttek\Tournament;

/**
 * How a stage pairs its participants.
 */
enum StageFormat: string
{
    /** Rounds paired by score groups, a set number of them. */
    case Swiss = 'swiss';
    /** Pools where everyone meets everyone, once or twice, by the Berger tables. */
    case RoundRobin = 'round-robin';
    /** A single-elimination bracket by seeds. */
    case SingleElimination = 'single-elimination';
}
