<?php

declare(strict_types=1);

namespace Tilttek\Exchange;

/**
 * Runs the steps of one exchange — the items that change hands, the points that move, the offer that closes — as one
 * unit: all of them, or none. A store on a database runs them in one of its transactions.
 */
interface Transaction
{
    /**
     * @template T
     *
     * @param callable(): T $steps
     *
     * @return T
     */
    public function run(callable $steps): mixed;
}
