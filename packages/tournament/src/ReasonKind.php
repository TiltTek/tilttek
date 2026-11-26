<?php

declare(strict_types=1);

namespace Tilttek\Tournament;

/**
 * Why a pairing is what it is: each kind is a stable code an application translates, with the participants and the
 * round of the reason.
 */
enum ReasonKind: string
{
    /** The participant has the bye: the lowest in the standings among those who never had one. */
    case Bye = 'bye';
    /** The participant has a bye a second time: every other participant left had one already. */
    case ByeRepeated = 'bye-repeated';
    /** The participant rests this round of the pool: the pool has an odd number of participants. */
    case Rest = 'rest';
    /** The participant plays first: they played second more often, or last. */
    case FirstPlayerAlternated = 'first-player-alternated';
    /** The participant plays first: both have the same history of sides, and the higher ranked alternates by table. */
    case FirstPlayerByRank = 'first-player-by-rank';
    /** The participant plays first again: both are due to play first, and the other is more so. */
    case FirstPlayerRepeated = 'first-player-repeated';
    /** The participant plays first: they played second when the two met before. */
    case FirstPlayerAlternatedRematch = 'first-player-alternated-rematch';
    /** The participant is not paired with another of the same score: the two met already, in the round given. */
    case RematchAvoided = 'rematch-avoided';
    /** The participant meets an opponent a second time: no pairing of the round avoided it. */
    case Rematch = 'rematch';
    /** The participant meets an opponent of another score: their own score group could not be paired whole. */
    case Floated = 'floated';
    /** The pairing comes from the seeds of the bracket. */
    case Seeded = 'seeded';
    /** The pairing comes from the Berger table of the pool. */
    case Scheduled = 'scheduled';
    /** The first player is swapped: this is the return match of the pool. */
    case ReturnMatch = 'return-match';
}
