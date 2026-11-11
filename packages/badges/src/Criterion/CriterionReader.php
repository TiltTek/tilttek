<?php

declare(strict_types=1);

namespace Tilttek\Badges\Criterion;

use DateMalformedStringException;
use DateTimeImmutable;
use DateTimeZone;
use Tilttek\Badges\Exception\InvalidBadge;

/**
 * Reads a criterion from plain values, as a configuration holds it: one key naming the kind of criterion.
 *
 * ```yaml
 * count: { verbs: ['news.commented'], times: 10 }
 * total: { verbs: ['book.read'], amount: 1000 }
 * first: { verbs: ['sheet.published'] }
 * streak: { verbs: ['site.visited'], days: 7 }
 * measure: { name: points, threshold: 1000, verbs: ['game.*'] }
 * manual: true
 * all: [{ first: { verbs: ['game.played'] } }, { first: { verbs: ['news.commented'] } }]
 * at_least: { count: 2, of: [...] }
 * ```
 *
 * A leaf also takes `context`, the facts the activity must hold, and `since` and `until`, the period of its moments,
 * read in the catalog's timezone.
 */
final readonly class CriterionReader
{
    public function __construct(
        private DateTimeZone $timezone = new DateTimeZone('UTC'),
    ) {
    }

    /**
     * @param array<array-key, mixed> $criterion
     */
    public function read(array $criterion): Criterion
    {
        if (1 !== \count($criterion)) {
            throw InvalidBadge::because('A criterion is one of count, total, first, streak, measure, manual, all and at_least, alone.');
        }

        $kind = array_key_first($criterion);
        $values = $criterion[$kind];

        if ('manual' === $kind) {
            return new Manual();
        }

        if (!\is_array($values)) {
            throw InvalidBadge::because(\sprintf('The criterion "%s" holds a list of values.', $kind));
        }

        return match ($kind) {
            'count' => new Count($this->scope($values), $this->integer($values, 'times')),
            'total' => new Total($this->scope($values), $this->integer($values, 'amount')),
            'first' => new Count($this->scope($values)),
            'streak' => new Streak($this->scope($values), $this->integer($values, 'days'), $this->timezone),
            'measure' => new Measure($this->scope($values), $this->string($values, 'name'), $this->integer($values, 'threshold')),
            'all' => new AtLeast($this->children($values)),
            'at_least' => new AtLeast($this->children($values['of'] ?? null), $this->integer($values, 'count')),
            default => throw InvalidBadge::because(\sprintf('"%s" is not a kind of criterion: count, total, first, streak, measure, manual, all or at_least.', $kind)),
        };
    }

    /**
     * @param array<array-key, mixed> $values
     */
    private function scope(array $values): Scope
    {
        $verbs = $values['verbs'] ?? null;
        $context = $values['context'] ?? [];

        if (!\is_array($verbs) || !\is_array($context)) {
            throw InvalidBadge::because('A criterion names its verbs as a list of patterns, and its context as values by name.');
        }

        $patterns = [];

        foreach ($verbs as $verb) {
            if (!\is_string($verb)) {
                throw InvalidBadge::because('A criterion names its verbs as a list of patterns.');
            }

            $patterns[] = $verb;
        }

        $conditions = [];

        foreach ($context as $name => $value) {
            if (!\is_string($name) || (!\is_bool($value) && !\is_int($value) && !\is_string($value))) {
                throw InvalidBadge::because('The context of a criterion holds integers, booleans and strings by name.');
            }

            $conditions[$name] = $value;
        }

        return new Scope($patterns, $conditions, $this->date($values, 'since'), $this->date($values, 'until'));
    }

    /**
     * @return list<Criterion>
     */
    private function children(mixed $values): array
    {
        if (!\is_array($values) || !array_is_list($values)) {
            throw InvalidBadge::because('A combination holds a list of criteria.');
        }

        $children = [];

        foreach ($values as $child) {
            if (!\is_array($child)) {
                throw InvalidBadge::because('A combination holds a list of criteria.');
            }

            $children[] = $this->read($child);
        }

        return $children;
    }

    /**
     * @param array<array-key, mixed> $values
     */
    private function integer(array $values, string $key): int
    {
        $value = $values[$key] ?? null;

        if (!\is_int($value)) {
            throw InvalidBadge::because(\sprintf('The "%s" of a criterion is an integer.', $key));
        }

        return $value;
    }

    /**
     * @param array<array-key, mixed> $values
     */
    private function string(array $values, string $key): string
    {
        $value = $values[$key] ?? null;

        if (!\is_string($value)) {
            throw InvalidBadge::because(\sprintf('The "%s" of a criterion is a string.', $key));
        }

        return $value;
    }

    /**
     * @param array<array-key, mixed> $values
     */
    private function date(array $values, string $key): ?DateTimeImmutable
    {
        $value = $values[$key] ?? null;

        if (null === $value) {
            return null;
        }

        try {
            return new DateTimeImmutable($this->string($values, $key), $this->timezone);
        } catch (DateMalformedStringException) {
            throw InvalidBadge::because(\sprintf('The "%s" of a criterion is a date; "%s" is not.', $key, $this->string($values, $key)));
        }
    }
}
