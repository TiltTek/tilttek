<?php

declare(strict_types=1);

namespace Tilttek\LuckTokens\Store;

use Override;
use Phil\Resource\ResourceReference;
use Tilttek\LuckTokens\Contract\TokenRules;
use Tilttek\LuckTokens\TableRules;

/**
 * The rules of the tables in memory, for tests and for an application that lives in one process.
 */
final class InMemoryTokenRules implements TokenRules
{
    /** @var array<string, TableRules> */
    private array $rules = [];

    /**
     * Keeps the rules of their table, in place of those it had.
     */
    public function save(TableRules $rules): void
    {
        $this->rules[$rules->table->toString()] = $rules;
    }

    #[Override]
    public function rulesFor(ResourceReference $table): ?TableRules
    {
        return $this->rules[$table->toString()] ?? null;
    }
}
