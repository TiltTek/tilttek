<?php

declare(strict_types=1);

namespace Tilttek\LuckTokensBundle\Infrastructure\Rules;

use Override;
use Phil\Resource\InvalidResource;
use Phil\Resource\ResourceReference;
use Tilttek\LuckTokens\Contract\TokenRules;
use Tilttek\LuckTokens\Exception\InvalidTokenRule;
use Tilttek\LuckTokens\TableRules;

/**
 * The rules of the tables the configuration lists under `tables`, read once when the service is built and never
 * changed after: one instance serves every request of a worker.
 */
final readonly class ConfiguredTokenRules implements TokenRules
{
    /** @var array<string, TableRules> */
    private array $rules;

    /**
     * @param array<string, array<array-key, mixed>> $tables the rules of each table, by reference, as `TableRules::fromArray()` reads them
     *
     * @throws InvalidTokenRule when a reference or rules are not ones the library takes
     */
    public function __construct(array $tables)
    {
        $rules = [];

        foreach ($tables as $reference => $definition) {
            try {
                $table = ResourceReference::fromString($reference);
            } catch (InvalidResource $invalidResource) {
                throw InvalidTokenRule::because($invalidResource->getMessage());
            }

            $rules[$table->toString()] = TableRules::fromArray($table, $definition);
        }

        $this->rules = $rules;
    }

    #[Override]
    public function rulesFor(ResourceReference $table): ?TableRules
    {
        return $this->rules[$table->toString()] ?? null;
    }
}
