<?php

declare(strict_types=1);

namespace Tilttek\FanAwards;

/**
 * Why the library refuses an act on an edition.
 */
enum Refusal: string
{
    case EditionExists = 'edition_exists';

    case WrongPhase = 'wrong_phase';

    case VoterNotEligible = 'voter_not_eligible';

    case Ineligible = 'ineligible';

    case TooManyCandidates = 'too_many_candidates';

    case NotCounted = 'not_counted';

    case NotAnnounced = 'not_announced';
}
