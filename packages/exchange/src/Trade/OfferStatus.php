<?php

declare(strict_types=1);

namespace Tilttek\Exchange\Trade;

/**
 * Where an offer stands: `Open` until the member it was made to accepts it — `Accepted` —, declines it — `Declined` —
 * or answers with an offer of their own — `Countered` —, until the member who made it withdraws it — `Withdrawn` —,
 * or until it outlives its lifetime — `Expired`. Only an open offer holds its items.
 */
enum OfferStatus: string
{
    case Open = 'open';

    case Accepted = 'accepted';

    case Declined = 'declined';

    case Withdrawn = 'withdrawn';

    case Countered = 'countered';

    case Expired = 'expired';
}
