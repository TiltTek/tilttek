<?php

declare(strict_types=1);

namespace Tilttek\Raffle;

/**
 * What a raffle puts at stake, which decides where its seed may come from.
 */
enum Stake: string
{
    /**
     * Something of value is won — a game, a ticket, a gift card —: the seed comes from a public beacon round announced
     * when the entries close, which the organiser cannot choose nor know in advance.
     */
    case Prize = 'prize';

    /**
     * Nothing of value is won — who speaks first, who brings the snacks —: a seed committed by the organiser when the
     * raffle opens is enough, and the raffle needs no network.
     */
    case None = 'none';
}
