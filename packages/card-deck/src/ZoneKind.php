<?php

declare(strict_types=1);

namespace Tilttek\CardDeck;

/**
 * The kinds of zones a card lies in.
 */
enum ZoneKind: string
{
    /** The draw pile: always face down, its order never shown to anyone. */
    case DrawPile = 'draw';

    /** The discard pile. */
    case Discard = 'discard';

    /** The table, in front of everyone. */
    case Table = 'table';

    /** The hand of one player, who sees every card of it. */
    case Hand = 'hand';

    /** A pile named by the game, for games of free zones. */
    case Pile = 'pile';
}
