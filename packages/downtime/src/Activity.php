<?php

declare(strict_types=1);

namespace Tilttek\Downtime;

use Tilttek\Downtime\Exception\InvalidDowntime;
use Tilttek\Downtime\Internal\Identifier;
use Tilttek\Downtime\Internal\Text;

/**
 * An activity the rules of a system declare for the time between sessions — crafting, research, training, carousing —:
 * how many days it takes, what it costs, the outcomes it may end with and how one is chosen. It is checked as a whole:
 * each outcome once, each cost's resource once, every outcome the resolution names declared.
 */
final readonly class Activity
{
    /** The longest name, in characters. */
    public const int MAX_NAME = 120;

    /** The longest description, in characters. */
    public const int MAX_DESCRIPTION = 4_000;

    /** The most days one activity takes. */
    public const int MAX_DAYS = 365;

    /** The most outcomes of one activity. */
    public const int MAX_OUTCOMES = 16;

    /** The most costs of one activity. */
    public const int MAX_COSTS = 8;

    public string $name;

    public string $description;

    /** @var list<Outcome> */
    public array $outcomes;

    /** @var list<Cost> */
    public array $costs;

    /**
     * @param string        $key         the key of the activity within the rules of its system
     * @param string        $name        one line of 1 to `MAX_NAME` characters
     * @param int           $days        1 to `MAX_DAYS`
     * @param list<Outcome> $outcomes    1 to `MAX_OUTCOMES`, each key once
     * @param list<Cost>    $costs       up to `MAX_COSTS`, each resource once
     * @param string        $description lines of up to `MAX_DESCRIPTION` characters, possibly empty
     *
     * @throws InvalidDowntime when a value is out of its shape or its bounds, a key or a resource comes twice, or the
     *                         resolution names an outcome the activity does not declare
     */
    public function __construct(
        public string $key,
        string $name,
        public int $days,
        array $outcomes,
        public Resolution $resolution,
        array $costs = [],
        string $description = '',
    ) {
        Identifier::key($key, 'an activity');
        $this->name = Text::line($name, self::MAX_NAME, 'name of an activity');
        $this->description = Text::block($description, self::MAX_DESCRIPTION, 'description of an activity');

        if ($days < 1 || $days > self::MAX_DAYS) {
            throw InvalidDowntime::because(\sprintf('An activity takes 1 to %d days.', self::MAX_DAYS));
        }

        if ([] === $outcomes || \count($outcomes) > self::MAX_OUTCOMES) {
            throw InvalidDowntime::because(\sprintf('An activity has 1 to %d outcomes.', self::MAX_OUTCOMES));
        }

        $keys = array_map(static fn (Outcome $outcome): string => $outcome->key, $outcomes);
        $twice = Identifier::twice($keys);

        if (null !== $twice) {
            throw InvalidDowntime::because(\sprintf('The outcome %s comes twice in the activity %s.', $twice, $key));
        }

        $missing = array_values(array_diff($resolution->outcomes(), $keys));

        if ([] !== $missing) {
            throw InvalidDowntime::because(\sprintf('The resolution of the activity %s names the outcome %s, which it does not declare.', $key, $missing[0]));
        }

        if (\count($costs) > self::MAX_COSTS) {
            throw InvalidDowntime::because(\sprintf('An activity has at most %d costs.', self::MAX_COSTS));
        }

        $twice = Identifier::twice(array_map(static fn (Cost $cost): string => $cost->resource, $costs));

        if (null !== $twice) {
            throw InvalidDowntime::because(\sprintf('The resource %s comes twice among the costs of the activity %s.', $twice, $key));
        }

        $this->outcomes = $outcomes;
        $this->costs = $costs;
    }

    public function outcome(string $key): ?Outcome
    {
        foreach ($this->outcomes as $outcome) {
            if ($outcome->key === $key) {
                return $outcome;
            }
        }

        return null;
    }

    /**
     * What the activity costs over its days, by resource, in the order of its costs.
     *
     * @return array<string, int>
     */
    public function totals(): array
    {
        $totals = [];

        foreach ($this->costs as $cost) {
            $totals[$cost->resource] = $cost->total($this->days);
        }

        return $totals;
    }
}
