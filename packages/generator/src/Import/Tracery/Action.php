<?php

declare(strict_types=1);

namespace Tilttek\Generator\Import\Tracery;

/**
 * `[name:rule]` or `[name:one,other]`: a variable set to one of its values, each a rule.
 *
 * @internal
 */
final readonly class Action
{
    /**
     * @param non-empty-list<list<Text|Tag|Action>> $values
     */
    public function __construct(public string $name, public array $values)
    {
    }
}
