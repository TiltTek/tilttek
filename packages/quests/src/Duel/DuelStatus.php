<?php

declare(strict_types=1);

namespace Tilttek\Quests\Duel;

/**
 * Where a duel stands: `Offered` until the member challenged accepts it — `Accepted` —, declines it — `Declined` —, or
 * until the challenger withdraws it — `Withdrawn`; once accepted, `Won` by the first to accomplish the quest, or
 * `Expired` when its time runs out with no winner. An offer left unanswered expires too.
 */
enum DuelStatus: string
{
    case Offered = 'offered';
    case Accepted = 'accepted';
    case Declined = 'declined';
    case Withdrawn = 'withdrawn';
    case Won = 'won';
    case Expired = 'expired';
}
