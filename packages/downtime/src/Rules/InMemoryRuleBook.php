<?php

declare(strict_types=1);

namespace Tilttek\Downtime\Rules;

use Override;
use Tilttek\Downtime\Activity;
use Tilttek\Downtime\Contract\RuleBook;
use Tilttek\Downtime\Exception\InvalidDowntime;
use Tilttek\Downtime\SystemRules;

/**
 * The rules of the systems an application declares once, as plain data shared between requests.
 */
final readonly class InMemoryRuleBook implements RuleBook
{
    /** @var array<string, SystemRules> by system */
    private array $systems;

    /**
     * @throws InvalidDowntime when two rules are of the same system
     */
    public function __construct(SystemRules ...$rules)
    {
        $systems = [];

        foreach ($rules as $rule) {
            if (isset($systems[$rule->system])) {
                throw InvalidDowntime::because(\sprintf('The rules of the system %s are given twice.', $rule->system));
            }

            $systems[$rule->system] = $rule;
        }

        $this->systems = $systems;
    }

    #[Override]
    public function activity(string $system, string $key): ?Activity
    {
        return ($this->systems[$system] ?? null)?->activity($key);
    }

    #[Override]
    public function activities(string $system): array
    {
        return ($this->systems[$system] ?? null)?->activities() ?? [];
    }
}
