<?php

declare(strict_types=1);

namespace Tilttek\Generator\Definition;

/**
 * One value of a generator's state: the template that gives it when there is no previous value, and the template
 * that gives the next one from the previous state — or none, and the previous value is kept.
 */
final readonly class StateValue
{
    public function __construct(public string $start, public ?string $next = null)
    {
    }
}
