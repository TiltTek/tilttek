<?php

declare(strict_types=1);

namespace Tilttek\AccessibleDice\Application;

use Tilttek\AccessibleDice\Application\Exception\InvalidChoice;
use Tilttek\Dice\Custom\CustomDice;
use Tilttek\Dice\Custom\DieDefinition;

/**
 * The dice the interface offers — standard dice by their number of sides, and custom dice by their name — and the
 * expression of what it sent: a die among those, how many of it, and a modifier. The expression is built from the
 * choice alone, so that the form rolls the same with or without its script.
 */
final readonly class AccessibleDice
{
    public const int MAX_MODIFIER = 1000;

    /** The standard dice offered when the configuration names none. */
    public const array SIDES = [4, 6, 8, 10, 12, 20, 100];

    /**
     * @var list<string>
     */
    private array $custom;

    /**
     * @param list<int>     $sides  the standard dice offered, by their number of sides
     * @param ?list<string> $custom the custom dice offered, by name; every custom die when null
     * @param int           $most   how many dice one choice rolls at most
     *
     * @throws InvalidChoice when a die cannot be offered
     */
    public function __construct(
        CustomDice $dice,
        private FaceDescriber $describer,
        private Words $words = new Words(),
        private array $sides = self::SIDES,
        ?array $custom = null,
        private int $most = 100,
    ) {
        foreach ($sides as $side) {
            if ($side < 2) {
                throw InvalidChoice::because(\sprintf('A standard die has at least 2 sides; %d is too few.', $side));
            }
        }

        foreach ($custom ?? [] as $name) {
            if (!$dice->has($name)) {
                throw InvalidChoice::because(\sprintf('No custom die is named "%s".', $name));
            }
        }

        if ($most < 1) {
            throw InvalidChoice::because('A choice rolls at least one die.');
        }

        $this->custom = $custom ?? array_map(static fn (DieDefinition $die): string => $die->name, $dice->all());
    }

    /**
     * @return list<DieChoice>
     */
    public function choices(?string $locale = null): array
    {
        $choices = [];

        foreach ($this->sides as $side) {
            $choices[] = new DieChoice('d'.$side, 'd'.$side, $this->words->word('die.standard', ['%sides%' => $side], $locale));
        }

        foreach ($this->custom as $name) {
            $choices[] = new DieChoice($name, 'd{'.$name.'}', $this->words->label($name, $locale), $this->describer->describe($name, $locale));
        }

        return $choices;
    }

    public function most(): int
    {
        return $this->most;
    }

    /**
     * The expression of a choice: `{"die": "d6", "count": "2", "modifier": "1"}` is `2d6+1`.
     *
     * @param array<array-key, mixed> $choice
     *
     * @throws InvalidChoice when the die is not offered, or the count or the modifier is out of range
     */
    public function expression(array $choice): string
    {
        $die = $choice['die'] ?? null;
        $notation = match (true) {
            \in_array($die, $this->standard(), true) => $die,
            \in_array($die, $this->custom, true) => 'd{'.$die.'}',
            default => throw InvalidChoice::because('The die is not one the interface offers.'),
        };

        $count = $this->integer($choice['count'] ?? 1, 1, $this->most, 'count');
        $modifier = $this->integer($choice['modifier'] ?? 0, -self::MAX_MODIFIER, self::MAX_MODIFIER, 'modifier');

        return \sprintf('%d%s%s', $count, $notation, 0 === $modifier ? '' : \sprintf('%+d', $modifier));
    }

    /**
     * @return list<string>
     */
    private function standard(): array
    {
        return array_map(static fn (int $side): string => 'd'.$side, $this->sides);
    }

    private function integer(mixed $value, int $least, int $most, string $what): int
    {
        $integer = \is_int($value) || \is_string($value) ? filter_var($value, \FILTER_VALIDATE_INT, ['options' => ['min_range' => $least, 'max_range' => $most]]) : false;

        if (false === $integer) {
            throw InvalidChoice::because(\sprintf('The %s is a whole number from %d to %d.', $what, $least, $most));
        }

        return $integer;
    }
}
