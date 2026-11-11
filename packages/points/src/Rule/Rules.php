<?php

declare(strict_types=1);

namespace Tilttek\Points\Rule;

use DateTimeZone;
use Exception;
use Tilttek\Activity\Activity;
use Tilttek\Points\Exception\InvalidPoints;

/**
 * The earning rules of an application, by name, sorted by name, and the timezone their periods are counted in.
 */
final readonly class Rules
{
    /**
     * @var array<string, EarningRule>
     */
    private array $rules;

    public function __construct(
        public DateTimeZone $timezone,
        EarningRule ...$rules,
    ) {
        $byName = [];

        foreach ($rules as $rule) {
            if (isset($byName[$rule->name])) {
                throw InvalidPoints::because(\sprintf('Two rules are named "%s".', $rule->name));
            }

            $byName[$rule->name] = $rule;
        }

        ksort($byName, \SORT_STRING);
        $this->rules = $byName;
    }

    /**
     * The rules from plain values, as a configuration gives them.
     *
     * @param array<string, array{verbs: list<string>, points: int, currency?: ?string, per_quantity?: bool, cap?: ?array{amount: int, period: string}, context?: array<string, bool|int|string>}> $rules
     */
    public static function fromArray(array $rules, string $timezone = 'UTC'): self
    {
        try {
            $zone = new DateTimeZone($timezone);
        } catch (Exception) {
            throw InvalidPoints::because(\sprintf('"%s" is not a timezone.', $timezone));
        }

        $list = [];

        foreach ($rules as $name => $rule) {
            $cap = $rule['cap'] ?? null;
            $list[] = new EarningRule(
                $name,
                $rule['verbs'],
                $rule['points'],
                $rule['currency'] ?? null,
                null === $cap ? null : new Cap($cap['amount'], Period::tryFrom($cap['period']) ?? throw InvalidPoints::because(\sprintf('"%s" is not a period: day, week, month, year or ever.', $cap['period']))),
                $rule['context'] ?? [],
                $rule['per_quantity'] ?? true,
            );
        }

        return new self($zone, ...$list);
    }

    /**
     * @return list<EarningRule>
     */
    public function all(): array
    {
        return array_values($this->rules);
    }

    /**
     * The rules that reward the activity, sorted by name.
     *
     * @return list<EarningRule>
     */
    public function matching(Activity $activity): array
    {
        return array_values(array_filter($this->rules, static fn (EarningRule $rule): bool => $rule->matches($activity)));
    }

    /**
     * The verb patterns the rules read, each once.
     *
     * @return list<string>
     */
    public function patterns(): array
    {
        $patterns = [];

        foreach ($this->rules as $rule) {
            foreach ($rule->verbs->patterns as $pattern) {
                $patterns[$pattern->value] = $pattern->value;
            }
        }

        return array_values($patterns);
    }
}
