<?php

declare(strict_types=1);

namespace Tilttek\Downtime;

use Tilttek\Downtime\Exception\InvalidDowntime;
use Tilttek\Downtime\Internal\Identifier;

/**
 * How an activity ends: always with one outcome, or with the outcome of the band its roll falls in. The bands follow
 * one another without overlap nor gap; a total below the first band falls in the first, one above the last in the
 * last. The dice expression is the system's own, read by the roller of the application.
 */
final readonly class Resolution
{
    /** The longest dice expression, in characters. */
    public const int MAX_DICE = 64;

    /** The most bands of one roll. */
    public const int MAX_BANDS = 16;

    /** Letters, digits, spaces and the signs of dice expressions, no space at either end. */
    private const string DICE = '/^[0-9A-Za-z+\-*\/()!<>=.,]([0-9A-Za-z+\-*\/()!<>=., ]{0,62}[0-9A-Za-z+\-*\/()!<>=.,])?$/D';

    /**
     * @param list<Band> $bands
     */
    private function __construct(public ?string $dice, public array $bands, public ?string $outcome)
    {
    }

    /**
     * The activity always ends with this outcome.
     *
     * @throws InvalidDowntime when the key is not one
     */
    public static function fixed(string $outcome): self
    {
        return new self(null, [], Identifier::key($outcome, 'an outcome'));
    }

    /**
     * The activity ends with a roll of the dice, its total read against the bands.
     *
     * @param string     $dice  1 to `MAX_DICE` letters, digits, spaces and signs, such as "1d20+5"
     * @param list<Band> $bands 1 to `MAX_BANDS`, in any order
     *
     * @throws InvalidDowntime when the expression is out of its shape, or the bands are none, too many, overlap or
     *                         leave a gap
     */
    public static function rolled(string $dice, array $bands): self
    {
        if (1 !== preg_match(self::DICE, $dice)) {
            throw InvalidDowntime::because(\sprintf('A dice expression is 1 to %d letters, digits, spaces or signs, without a space at either end.', self::MAX_DICE));
        }

        if ([] === $bands || \count($bands) > self::MAX_BANDS) {
            throw InvalidDowntime::because(\sprintf('A roll has 1 to %d bands.', self::MAX_BANDS));
        }

        usort($bands, static fn (Band $a, Band $b): int => $a->from <=> $b->from);

        for ($i = 1, $count = \count($bands); $i < $count; ++$i) {
            if ($bands[$i]->from !== $bands[$i - 1]->to + 1) {
                throw InvalidDowntime::because(\sprintf('The bands of a roll follow one another without overlap nor gap: %d follows %d.', $bands[$i]->from, $bands[$i - 1]->to));
            }
        }

        return new self($dice, $bands, null);
    }

    public function isRolled(): bool
    {
        return null !== $this->dice;
    }

    /**
     * The outcome a roll of this total ends with; the outcome of a fixed resolution whatever the total.
     */
    public function outcomeFor(int $total): string
    {
        if (null !== $this->outcome) {
            return $this->outcome;
        }

        $last = $this->bands[\count($this->bands) - 1];

        foreach ($this->bands as $band) {
            if ($total <= $band->to) {
                return $band->outcome;
            }
        }

        return $last->outcome;
    }

    /**
     * The keys of the outcomes the resolution may end with, each once.
     *
     * @return list<string>
     */
    public function outcomes(): array
    {
        if (null !== $this->outcome) {
            return [$this->outcome];
        }

        return array_values(array_unique(array_map(static fn (Band $band): string => $band->outcome, $this->bands)));
    }
}
