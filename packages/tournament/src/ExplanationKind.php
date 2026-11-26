<?php

declare(strict_types=1);

namespace Tilttek\Tournament;

/**
 * Why two participants meet in a round, or why they do not: a stable code an application translates.
 */
enum ExplanationKind: string
{
    /** They meet at a table of the round. */
    case Paired = 'paired';
    /** They played each other over the board already, in the round given. */
    case AlreadyMet = 'already-met';
    /** One of them has the bye, or rests: the participant given. */
    case Bye = 'bye';
    /** One of them is not in the round: they withdrew, or are not in the stage: the participant given. */
    case Absent = 'absent';
    /** They have different points, and each was paired in their own score group or nearer to it. */
    case DifferentScores = 'different-scores';
    /** They are in different pools. */
    case DifferentPools = 'different-pools';
    /** The schedule of the pool or the bracket sets their meeting in another round, or after this one. */
    case Scheduled = 'scheduled';
    /** They have the same points and never met: another pairing of the group cost less — sides or halves. */
    case OtherPairing = 'other-pairing';
}
