<?php

declare(strict_types=1);

namespace Tilttek\RulesLookup;

/**
 * One page of the rules a store found for a query, best first, and how many it found in all.
 */
final readonly class RuleMatches
{
    /**
     * @param list<RuleMatch> $matches the page asked for, best first
     * @param int             $total   how many rules answer the query, on every page
     */
    public function __construct(public array $matches, public int $total)
    {
    }
}
