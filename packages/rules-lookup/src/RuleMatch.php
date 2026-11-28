<?php

declare(strict_types=1);

namespace Tilttek\RulesLookup;

/**
 * A rule a store found for a query, with its score: the higher, the better it answers.
 */
final readonly class RuleMatch
{
    public function __construct(public Rule $rule, public int $score)
    {
    }
}
