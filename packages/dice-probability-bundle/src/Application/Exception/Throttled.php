<?php

declare(strict_types=1);

namespace Tilttek\DiceProbabilityBundle\Application\Exception;

use Override;
use RuntimeException;

/**
 * A client asked for more computations than its rate limit allows: the expression is not computed until the limit
 * lets it again.
 */
final class Throttled extends RuntimeException implements Refusal
{
    public function __construct()
    {
        parent::__construct('Too many computations were asked for in a short time; the next ones wait for the rate limit.');
    }

    #[Override]
    public function key(): string
    {
        return 'refusal.throttled';
    }

    #[Override]
    public function parameters(): array
    {
        return [];
    }
}
