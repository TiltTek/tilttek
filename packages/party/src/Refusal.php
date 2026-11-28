<?php

declare(strict_types=1);

namespace Tilttek\Party;

/**
 * Why an act on a party is refused.
 */
enum Refusal: string
{
    case AlreadyFormed = 'already_formed';

    case AlreadyMember = 'already_member';

    case NotMember = 'not_member';

    case Full = 'full';

    case TooManyParties = 'too_many_parties';

    case LootExists = 'loot_exists';

    case TooMuchLoot = 'too_much_loot';

    case NotEnoughLoot = 'not_enough_loot';

    case NoteExists = 'note_exists';

    case TooManyNotes = 'too_many_notes';

    case NotEnoughFunds = 'not_enough_funds';

    case NoOneToShare = 'no_one_to_share';
}
