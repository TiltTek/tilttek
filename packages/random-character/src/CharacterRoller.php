<?php

declare(strict_types=1);

namespace Tilttek\RandomCharacter;

use Random\Engine\Secure;
use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer;
use Tilttek\Dice\DiceRoller;
use Tilttek\Dice\Exception\DiceException;
use Tilttek\Dice\Expression\Pool;
use Tilttek\Generator\Definition\Attribution;
use Tilttek\Generator\Draw;
use Tilttek\Generator\Exception\GeneratorException;
use Tilttek\Generator\Generator;
use Tilttek\RandomCharacter\Contract\CharacterObserver;
use Tilttek\RandomCharacter\Event\CharacterRolled;
use Tilttek\RandomCharacter\Exception\InvalidRules;
use Tilttek\RandomCharacter\Exception\InvalidSeed;
use Tilttek\RandomCharacter\Exception\RollFailed;
use Tilttek\RpgSystems\GameSystem;
use Tilttek\RpgSystems\Systems;

/**
 * Rolls characters by creation rules: the attribute values by `tilttek/dice`, within the bounds of the system of
 * `tilttek/rpg-systems`, and the fields by the generators of `tilttek/generator` or by dice.
 *
 * Every character has a seed — the caller's, or one drawn — and the same seed with the same rules and tables gives the
 * same character. Each part of a character draws from a seed of its own, derived from the character's seed and the
 * part's name: a field added to the rules leaves the values of the others as they were.
 *
 * It keeps nothing between two calls: one instance serves every request of a worker.
 */
final readonly class CharacterRoller
{
    /** The longest seed, in characters. */
    public const int SEED_LENGTH = 256;

    /**
     * @param Systems    $systems    the systems whose attributes and bounds the rules fill
     * @param ?Generator $generator  the generators the fields draw from; none for rules that draw from none
     * @param DiceRoller $dice       the roller of the stats and of the rolled fields, with the application's limits
     * @param Randomizer $randomizer the randomness of the seeds drawn for characters rolled without one
     */
    public function __construct(
        private Systems $systems,
        private ?Generator $generator = null,
        private DiceRoller $dice = new DiceRoller(),
        private Randomizer $randomizer = new Randomizer(new Secure()),
        private ?CharacterObserver $observer = null,
    ) {
    }

    /**
     * What keeps the rules from being rolled: an unknown system, attributes it does not have, fixed values outside
     * their bounds, generators that do not exist. A generator named by a placeholder is checked when it is drawn.
     *
     * @return list<string> the problems, empty when there are none
     */
    public function check(CreationRules $rules): array
    {
        if (!$this->systems->has($rules->system)) {
            return [\sprintf('The system "%s" is unknown.', $rules->system)];
        }

        $problems = $rules->stats instanceof Stats ? $this->checkStats($rules->stats, $this->systems->get($rules->system)) : [];

        foreach ($rules->fields as $field) {
            if (FieldKind::Generated !== $field->kind || [] !== $field->references) {
                continue;
            }

            if (!$this->generator instanceof Generator) {
                $problems[] = \sprintf('The field "%s" draws from a generator, and the roller has none.', $field->key);
            } elseif (!$this->generator->catalog()->hasGenerator((string) $field->generator)) {
                $problems[] = \sprintf('The field "%s" draws from the generator "%s", which is unknown.', $field->key, $field->generator);
            }
        }

        return $problems;
    }

    /**
     * Rolls a character.
     *
     * @param ?string $seed the seed that makes the same character come again; one is drawn when null, and given back
     *
     * @throws InvalidSeed  when the seed is empty, not UTF-8 or longer than `SEED_LENGTH`
     * @throws InvalidRules when `check()` finds problems
     * @throws RollFailed   when a value falls outside its bounds, or a generator or the dice fail
     */
    public function roll(CreationRules $rules, ?string $seed = null): RandomCharacter
    {
        if (null !== $seed) {
            $this->checkSeed($seed);
        }

        $problems = $this->check($rules);

        if ([] !== $problems) {
            throw InvalidRules::problems($rules->key, $problems);
        }

        $given = null !== $seed;
        $seed ??= $this->randomizer->getBytesFromString('0123456789abcdef', 32);
        $system = $this->systems->get($rules->system);
        $stats = $rules->stats instanceof Stats ? $this->stats($rules->stats, $system, $seed) : [];
        $fields = [];
        $attributions = [];

        foreach ($rules->fields as $field) {
            $fields[$field->key] = FieldKind::Rolled === $field->kind
                ? $this->rolled($field, $this->randomizer($seed, 'field.'.$field->key))
                : $this->generated($field, $fields, $seed, $attributions);
        }

        $this->observer?->notify(new CharacterRolled($rules->key, $rules->system, $given));

        return new RandomCharacter($rules->key, $rules->system, $seed, $stats, $fields, array_values($attributions));
    }

    /**
     * @throws InvalidSeed
     */
    private function checkSeed(string $seed): void
    {
        if ('' === $seed) {
            throw InvalidSeed::because('A seed is not empty.');
        }

        if (!mb_check_encoding($seed, 'UTF-8')) {
            throw InvalidSeed::because('A seed is written in UTF-8.');
        }

        if (mb_strlen($seed) > self::SEED_LENGTH) {
            throw InvalidSeed::because(\sprintf('A seed is at most %d characters long.', self::SEED_LENGTH));
        }
    }

    /**
     * The seed of one part of the character, as 64 hexadecimal digits.
     */
    private function part(string $seed, string $part): string
    {
        return hash('sha256', $seed."\0".$part);
    }

    private function randomizer(string $seed, string $part): Randomizer
    {
        return new Randomizer(new Xoshiro256StarStar(hash('sha256', $seed."\0".$part, true)));
    }

    /**
     * @return list<string>
     */
    private function checkStats(Stats $stats, GameSystem $system): array
    {
        $problems = [];

        foreach ($stats->attributes as $attribute) {
            if (!$system->hasAttribute($attribute)) {
                $problems[] = \sprintf('The attribute "%s" is not one of the system "%s".', $attribute, $system->key);

                continue;
            }

            foreach ($stats->values as $value) {
                if (!$system->attribute($attribute)->allows($value)) {
                    $problems[] = \sprintf('The value %d of the array is outside the bounds of the attribute "%s".', $value, $attribute);
                }
            }
        }

        return $problems;
    }

    /**
     * @return array<string, int>
     *
     * @throws RollFailed
     */
    private function stats(Stats $stats, GameSystem $system, string $seed): array
    {
        $randomizer = $this->randomizer($seed, 'stats');
        $values = match ($stats->method) {
            StatMethod::Array => $randomizer->shuffleArray($stats->values),
            StatMethod::RolledArray => $randomizer->shuffleArray(array_map(fn (): int => $this->total($stats->pool(), $randomizer, 'the stats'), $stats->attributes)),
            StatMethod::Each => array_map(fn (string $attribute): int => $this->total($stats->pool(), $randomizer, 'the stats'), $stats->attributes),
        };
        $byAttribute = array_combine($stats->attributes, $values);

        foreach ($byAttribute as $attribute => $value) {
            $definition = $system->attribute($attribute);

            if (!$definition->allows($value)) {
                throw RollFailed::because(\sprintf('The stats gave %d to the attribute "%s", outside its bounds of %s to %s.', $value, $attribute, $definition->minimum ?? '-∞', $definition->maximum ?? '∞'));
            }
        }

        return $byAttribute;
    }

    /**
     * @return int|list<int>
     *
     * @throws RollFailed
     */
    private function rolled(Field $field, Randomizer $randomizer): int|array
    {
        $what = \sprintf('the field "%s"', $field->key);

        if (1 === $field->count) {
            return $this->total($field->pool(), $randomizer, $what);
        }

        $totals = [];

        for ($roll = 0; $roll < $field->count; ++$roll) {
            $totals[] = $this->total($field->pool(), $randomizer, $what);
        }

        return $totals;
    }

    /**
     * @param array<string, mixed>       $fields       the values rolled so far
     * @param array<string, Attribution> $attributions the attributions so far, by what they say, completed with the draw's
     *
     * @return string|array<mixed>|null
     *
     * @throws RollFailed
     */
    private function generated(Field $field, array $fields, string $seed, array &$attributions): string|array|null
    {
        // `check()` refused the rules if a generated field had no generator to draw from.
        $generator = $this->generator ?? throw RollFailed::because('The roller has no generator.');
        $name = $field->generatorFor($fields);

        if (!$generator->catalog()->hasGenerator($name)) {
            throw RollFailed::because(\sprintf('The field "%s" draws from the generator "%s", which is unknown.', $field->key, $name));
        }

        try {
            $result = $generator->draw($name, new Draw($field->count, $field->unique, $this->part($seed, 'field.'.$field->key)));
        } catch (GeneratorException $generatorException) {
            throw RollFailed::because(\sprintf('The generator "%s" of the field "%s" failed: %s', $name, $field->key, $generatorException->getMessage()), $generatorException);
        }

        if ($result->isShort()) {
            throw RollFailed::because(\sprintf('The generator "%s" gave %d of the %d different results the field "%s" asks for.', $name, \count($result->results), $field->count, $field->key));
        }

        foreach ($result->attributions as $attribution) {
            // Two draws that used the same table give equal attributions, kept once, by what they say, where first used.
            $attributions[json_encode($attribution->toArray(), \JSON_THROW_ON_ERROR)] = $attribution;
        }

        if (1 === $field->count) {
            return $this->value($result->first());
        }

        return array_map($this->value(...), $result->results);
    }

    /**
     * A result of a generator: a text, or the array of a structured result or a map.
     *
     * @return string|array<mixed>|null
     */
    private function value(mixed $result): string|array|null
    {
        return \is_string($result) || \is_array($result) ? $result : null;
    }

    /**
     * @throws RollFailed
     */
    private function total(Pool $pool, Randomizer $randomizer, string $what): int
    {
        try {
            $total = $this->dice->roll($pool, $randomizer)->totals()[0] ?? null;
        } catch (DiceException $diceException) {
            throw RollFailed::because(\sprintf('The roll of %s failed: %s', $what, $diceException->getMessage()), $diceException);
        }

        return $total ?? throw RollFailed::because(\sprintf('The roll of %s gives no number.', $what));
    }
}
