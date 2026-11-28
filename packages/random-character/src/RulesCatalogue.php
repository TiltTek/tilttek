<?php

declare(strict_types=1);

namespace Tilttek\RandomCharacter;

use Countable;
use Tilttek\RandomCharacter\Exception\InvalidRules;
use Tilttek\RandomCharacter\Exception\UnknownRules;

/**
 * The creation rules of an application, by key: an immutable value that requests share.
 */
final readonly class RulesCatalogue implements Countable
{
    /** The most rules of one catalogue. */
    public const int MAX_RULES = 1_000;

    /**
     * @var array<string, CreationRules>
     */
    private array $rules;

    /**
     * @throws InvalidRules
     */
    public function __construct(CreationRules ...$rules)
    {
        if (\count($rules) > self::MAX_RULES) {
            throw InvalidRules::because(\sprintf('A catalogue holds at most %d creation rules.', self::MAX_RULES));
        }

        $byKey = [];

        foreach ($rules as $rule) {
            if (isset($byKey[$rule->key])) {
                throw InvalidRules::because(\sprintf('Two creation rules have the key "%s".', $rule->key));
            }

            $byKey[$rule->key] = $rule;
        }

        $this->rules = $byKey;
    }

    public function has(string $key): bool
    {
        return isset($this->rules[$key]);
    }

    /**
     * @throws UnknownRules
     */
    public function get(string $key): CreationRules
    {
        return $this->rules[$key] ?? throw UnknownRules::key($key);
    }

    /**
     * @return array<string, CreationRules> by key, in the order they were given
     */
    public function all(): array
    {
        return $this->rules;
    }

    /**
     * The rules of one system, by key.
     *
     * @return array<string, CreationRules>
     */
    public function forSystem(string $system): array
    {
        return array_filter($this->rules, static fn (CreationRules $rules): bool => $rules->system === $system);
    }

    public function count(): int
    {
        return \count($this->rules);
    }
}
