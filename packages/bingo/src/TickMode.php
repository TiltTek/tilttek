<?php

declare(strict_types=1);

namespace Tilttek\Bingo;

/**
 * What ticks the squares of a game's cards.
 */
enum TickMode: string
{
    /**
     * The gestures of each player in the activity journal: the convention card — attend a talk, visit a booth. The
     * server ticks the card as the gestures are recorded, and a completed pattern wins on its own.
     */
    case Activity = 'activity';

    /**
     * The squares the host calls, for every card at once: the bingo of a session. A card wins when its player claims
     * it and the server finds a pattern among the squares called.
     */
    case Called = 'called';
}
