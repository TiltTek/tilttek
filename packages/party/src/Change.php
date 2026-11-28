<?php

declare(strict_types=1);

namespace Tilttek\Party;

/**
 * What happened to a party, one of its members, its purse, its loot or its notes.
 */
enum Change: string
{
    case Formed = 'formed';

    case Updated = 'updated';

    case Disbanded = 'disbanded';

    case Joined = 'joined';

    case Left = 'left';

    case RolesAssigned = 'roles_assigned';

    case StandingChanged = 'standing_changed';

    case Deposited = 'deposited';

    case Withdrawn = 'withdrawn';

    case Distributed = 'distributed';

    case LootAdded = 'loot_added';

    case LootGiven = 'loot_given';

    case LootRemoved = 'loot_removed';

    case NoteWritten = 'note_written';

    case NoteEdited = 'note_edited';

    case NoteRemoved = 'note_removed';

    case Forgotten = 'forgotten';
}
