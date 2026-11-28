<?php

declare(strict_types=1);

namespace Tilttek\RandomCharacter;

use Tilttek\Dice\Expression\Pool;
use Tilttek\RandomCharacter\Exception\InvalidRules;
use Tilttek\RandomCharacter\Internal\Check;

/**
 * The attribute values of the rules: the attributes of the system they fill, in order, and how — a roll for each, a
 * roll per attribute dealt at random, or a fixed array dealt at random.
 */
final readonly class Stats
{
    /** The most attributes the rules fill. */
    public const int MAX_ATTRIBUTES = 32;

    /** The largest value of a fixed array, either way from zero. */
    public const int MAX_VALUE = 1_000_000;

    /** The roll in the canonical notation of `tilttek/dice`, `4d6kh3`, or null for a fixed array. */
    public ?string $roll;

    private ?Pool $pool;

    /**
     * @param list<string> $attributes the keys of the system's attributes, `str`, each once
     * @param ?string      $roll       the roll of each value, for the methods `each` and `rolled-array`
     * @param list<int>    $values     the fixed values, one per attribute, for the method `array`
     *
     * @throws InvalidRules
     */
    public function __construct(
        public StatMethod $method,
        public array $attributes,
        ?string $roll = null,
        public array $values = [],
    ) {
        if ([] === $attributes || \count($attributes) > self::MAX_ATTRIBUTES) {
            throw InvalidRules::because(\sprintf('The stats fill 1 to %d attributes.', self::MAX_ATTRIBUTES));
        }

        foreach ($attributes as $attribute) {
            Check::key($attribute, 'an attribute');
        }

        if (\count(array_unique($attributes)) !== \count($attributes)) {
            throw InvalidRules::because('The stats name each attribute once.');
        }

        if (StatMethod::Array === $method) {
            $this->array($roll, $values);
            $this->roll = null;
            $this->pool = null;

            return;
        }

        if (null === $roll || [] !== $values) {
            throw InvalidRules::because(\sprintf('The stats rolled by the method "%s" have a roll and no fixed values.', $method->value));
        }

        [$this->roll, $this->pool] = Check::dice($roll, 'the stats');
    }

    /**
     * The roll of each value, ready for `tilttek/dice`.
     *
     * @throws InvalidRules for a fixed array, which has none
     */
    public function pool(): Pool
    {
        return $this->pool ?? throw InvalidRules::because('The stats of a fixed array have no roll.');
    }

    /**
     * @param list<int> $values
     *
     * @throws InvalidRules
     */
    private function array(?string $roll, array $values): void
    {
        if (null !== $roll || \count($values) !== \count($this->attributes)) {
            throw InvalidRules::because('The stats of a fixed array have no roll, and one value per attribute.');
        }

        foreach ($values as $value) {
            if (abs($value) > self::MAX_VALUE) {
                throw InvalidRules::because(\sprintf('A value of the stats lies from %d to %d.', -self::MAX_VALUE, self::MAX_VALUE));
            }
        }
    }
}
