<?php

declare(strict_types=1);

namespace Tilttek\Points;

/**
 * The sort of an account of the ledger. Points live in members' accounts; the other accounts are where they come from
 * and where they go, so that no point is born or lost without a movement:
 *
 * - `Member`: a member's points, the only account a balance is shown for;
 * - `Issuance`: where points are issued from — an earning rule, a manual grant —, its balance negative by what it gave;
 * - `Redemption`: where spent points go — a reward, a discount, a reservation;
 * - `Expiry`: where expired points go;
 * - `Erased`: what stands for an erased member in other members' movements, a transfer they received or gave.
 */
enum AccountType: string
{
    case Member = 'member';

    case Issuance = 'issuance';

    case Redemption = 'redemption';

    case Expiry = 'expiry';

    case Erased = 'erased';
}
