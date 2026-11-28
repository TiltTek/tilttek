<?php

declare(strict_types=1);

namespace Tilttek\RulesLookup\Contract;

use Tilttek\RulesLookup\Rule;
use Tilttek\RulesLookup\RuleId;
use Tilttek\RulesLookup\RuleMatches;
use Tilttek\RulesLookup\RuleQuery;

/**
 * Where the rules are kept and searched: in memory with `InMemoryRuleStore`, or in a database or a search engine by
 * an adapter of the application's own.
 */
interface RuleStore
{
    /**
     * The rule of an identifier; null when the store holds none.
     */
    public function get(RuleId $id): ?Rule;

    /**
     * The rules that answer a query — every word found in the title or the text a reader of its locale sees, narrowed
     * by its filters —, best first, the page the query asks for.
     */
    public function search(RuleQuery $query): RuleMatches;

    /**
     * The rules that refer to a rule, in the order the store holds them.
     *
     * @return list<Rule>
     */
    public function referencing(RuleId $id): array;
}
