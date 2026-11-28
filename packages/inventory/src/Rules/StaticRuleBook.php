<?php

declare(strict_types=1);

namespace Tilttek\Inventory\Rules;

use Tilttek\Inventory\CarryRules;
use Tilttek\Inventory\Contract\RuleBook;
use Tilttek\Inventory\Text;

/**
 * Carrying rules given by hand, by system key, for tests and for an application whose systems are fixed.
 */
final readonly class StaticRuleBook implements RuleBook
{
    /** @var array<string, CarryRules> */
    private array $rules;

    /**
     * @param array<string, CarryRules> $rules by system key
     */
    public function __construct(array $rules = [])
    {
        foreach (array_keys($rules) as $system) {
            Text::key($system, 'a game system');
        }

        $this->rules = $rules;
    }

    public function rules(string $system): ?CarryRules
    {
        return $this->rules[$system] ?? null;
    }
}
