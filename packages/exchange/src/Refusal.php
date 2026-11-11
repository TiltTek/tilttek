<?php

declare(strict_types=1);

namespace Tilttek\Exchange;

/**
 * Why an exchange is refused, for the application to tell the member in its own words.
 */
enum Refusal: string
{
    /** Every unit of the reward is reserved or delivered. */
    case OutOfStock = 'out_of_stock';

    /** The reward's period of availability has not started. */
    case NotYetAvailable = 'not_yet_available';

    /** The reward's period of availability is over. */
    case NoLongerAvailable = 'no_longer_available';

    /** The member holds as many units of the reward as one member may. */
    case MemberLimitReached = 'member_limit_reached';

    /** The member does not meet a condition of the reward: a level, a badge. */
    case RequirementUnmet = 'requirement_unmet';

    /** A member exchanging with themselves. */
    case SelfExchange = 'self_exchange';

    /** A member whose account is too new to exchange with others. */
    case NewMember = 'new_member';

    /** The member has given, or offered, as much as the period allows. */
    case AllowanceReached = 'allowance_reached';

    /** An item does not belong to the member who would give it. */
    case NotOwned = 'not_owned';

    /** An item the application keeps bound to its member. */
    case NotTradable = 'not_tradable';

    /** An item already held by another open offer. */
    case AlreadyHeld = 'already_held';

    /** A member acting on an offer that is not theirs to act on. */
    case NotParty = 'not_party';
}
