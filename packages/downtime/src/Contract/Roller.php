<?php

declare(strict_types=1);

namespace Tilttek\Downtime\Contract;

/**
 * Rolls the dice of an expression the system declares, for the activities resolved without a roll from the table.
 */
interface Roller
{
    /**
     * The total of one roll of the expression.
     */
    public function roll(string $dice): int;
}
