<?php

declare(strict_types=1);

namespace Tilttek\SessionScheduler\Contract;

use Tilttek\SessionScheduler\DateProposal;

/**
 * Puts dates to the members of a series: a scheduling poll, a message, a grid of availabilities. The answer comes back
 * later, when the application calls `SessionScheduler::add()` or `SessionScheduler::reschedule()` with the date chosen.
 */
interface DateProposer
{
    public function propose(DateProposal $proposal): void;
}
