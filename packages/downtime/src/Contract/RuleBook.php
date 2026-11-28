<?php

declare(strict_types=1);

namespace Tilttek\Downtime\Contract;

use Tilttek\Downtime\Activity;

/**
 * The activities the rules of each game system declare.
 */
interface RuleBook
{
    /**
     * The activity of the key in the rules of the system, or null when it declares none.
     */
    public function activity(string $system, string $key): ?Activity;

    /**
     * The activities the system declares, in its order; none for a system without rules.
     *
     * @return list<Activity>
     */
    public function activities(string $system): array;
}
