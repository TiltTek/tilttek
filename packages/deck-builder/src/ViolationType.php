<?php

declare(strict_types=1);

namespace Tilttek\DeckBuilder;

/**
 * The rule a deck breaks.
 */
enum ViolationType: string
{
    /** Fewer cards than the format's smallest deck. */
    case TooFewCards = 'too-few-cards';

    /** More cards than the format's largest deck. */
    case TooManyCards = 'too-many-cards';

    /** More copies of a card than the card or the format allows. */
    case TooManyCopies = 'too-many-copies';

    /** More copies of a card than a restriction of the format or of its taboo list allows. */
    case Restricted = 'restricted';

    /** A card the format or its taboo list bans. */
    case Banned = 'banned';

    /** A card the card catalog does not hold. */
    case UnknownCard = 'unknown-card';

    /** More copies of a card than the collection owns. */
    case NotOwned = 'not-owned';
}
