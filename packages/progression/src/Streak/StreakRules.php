<?php

declare(strict_types=1);

namespace Tilttek\Progression\Streak;

use DateTimeZone;
use Exception;
use Tilttek\Progression\Exception\InvalidProgression;
use Tilttek\Progression\Exception\UnknownStreak;

/**
 * The streaks of an application, by name, sorted by name, and the timezone a member's days are read in when nothing
 * says theirs.
 */
final readonly class StreakRules
{
    /**
     * @var array<string, StreakRule>
     */
    private array $rules;

    public function __construct(
        public DateTimeZone $timezone,
        StreakRule ...$rules,
    ) {
        $byName = [];

        foreach ($rules as $rule) {
            if (isset($byName[$rule->name])) {
                throw InvalidProgression::because(\sprintf('Two streaks are named "%s".', $rule->name));
            }

            $byName[$rule->name] = $rule;
        }

        ksort($byName, \SORT_STRING);
        $this->rules = $byName;
    }

    /**
     * The streaks from plain values, as a configuration gives them: `['daily-play' => ['verbs' => ['game.played'],
     * 'unit' => 'day', 'grace' => ['allowed' => 1, 'window' => 7]]]`.
     *
     * @param array<string, array{verbs: list<string>, unit?: string, grace?: ?array{allowed: int, window: int}, context?: array<string, bool|int|string>}> $rules
     */
    public static function fromArray(array $rules, string $timezone = 'UTC'): self
    {
        try {
            $zone = new DateTimeZone($timezone);
        } catch (Exception) {
            throw InvalidProgression::because(\sprintf('"%s" is not a timezone.', $timezone));
        }

        $list = [];

        foreach ($rules as $name => $rule) {
            $unit = $rule['unit'] ?? 'day';
            $grace = $rule['grace'] ?? null;
            $list[] = new StreakRule(
                $name,
                $rule['verbs'],
                StreakUnit::tryFrom($unit) ?? throw InvalidProgression::because(\sprintf('"%s" is not a streak unit: day or week.', $unit)),
                null === $grace ? null : new Grace($grace['allowed'], $grace['window']),
                $rule['context'] ?? [],
            );
        }

        return new self($zone, ...$list);
    }

    /**
     * @return list<StreakRule>
     */
    public function all(): array
    {
        return array_values($this->rules);
    }

    /**
     * @throws UnknownStreak
     */
    public function get(string $name): StreakRule
    {
        return $this->rules[$name] ?? throw UnknownStreak::named($name);
    }

    /**
     * The verb patterns the streaks read, each once.
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
