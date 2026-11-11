<?php

declare(strict_types=1);

namespace Tilttek\Exchange;

use Override;

/**
 * Runs the steps as they come, for the stores in memory: the exchanges check what they can before their first write,
 * and nothing undoes a step when a later one fails.
 */
final readonly class ImmediateTransaction implements Transaction
{
    #[Override]
    public function run(callable $steps): mixed
    {
        return $steps();
    }
}
