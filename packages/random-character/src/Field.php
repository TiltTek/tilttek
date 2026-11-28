<?php

declare(strict_types=1);

namespace Tilttek\RandomCharacter;

use Tilttek\Dice\Expression\Pool;
use Tilttek\Generator\Loading\Reader;
use Tilttek\RandomCharacter\Exception\InvalidRules;
use Tilttek\RandomCharacter\Exception\RollFailed;
use Tilttek\RandomCharacter\Internal\Check;

/**
 * A value of the character besides its attributes: drawn from a generator — a name, a trait — or rolled — an age.
 *
 * The generator a field draws from may depend on fields before it: `names.{ancestry}` draws from `names.elf` when the
 * field `ancestry` gave `elf`, and `names.{ancestry.key}` reads the value `key` of a structured result.
 */
final readonly class Field
{
    /** The most values one field gives. */
    public const int MAX_COUNT = 16;

    /** The longest name of a generator, placeholders included. */
    public const int GENERATOR_LENGTH = 256;

    private const string PLACEHOLDER = '/\{([a-z][a-z0-9_]{0,63})(?:\.([A-Za-z_]\w{0,63}))?\}/';

    /**
     * @var list<string> the fields the generator's name reads, each once, in the order it names them
     */
    public array $references;

    /**
     * @param ?string $generator the generator's name, with its placeholders, for a generated field
     * @param ?string $roll      the roll in the canonical notation of `tilttek/dice`, for a rolled field
     */
    private function __construct(
        public string $key,
        public FieldKind $kind,
        public ?string $generator,
        public ?string $roll,
        private ?Pool $pool,
        public int $count,
        public bool $unique,
    ) {
        Check::field($key);

        if ($count < 1 || $count > self::MAX_COUNT) {
            throw InvalidRules::because(\sprintf('The field "%s" gives 1 to %d values.', $key, self::MAX_COUNT));
        }

        $this->references = null === $generator ? [] : $this->references($generator);
    }

    /**
     * A field drawn from a generator.
     *
     * @param string $generator the name of the generator, `names.{ancestry}`
     * @param int    $count     how many results it draws: one gives a value, more give a list
     * @param bool   $unique    whether those results differ from each other
     *
     * @throws InvalidRules
     */
    public static function generated(string $key, string $generator, int $count = 1, bool $unique = false): self
    {
        return new self($key, FieldKind::Generated, $generator, null, null, $count, $unique);
    }

    /**
     * A field rolled with dice.
     *
     * @param string $notation the roll, `2d10+16`, read by `tilttek/dice`
     * @param int    $count    how many rolls: one gives a number, more give a list
     *
     * @throws InvalidRules
     */
    public static function rolled(string $key, string $notation, int $count = 1): self
    {
        [$roll, $pool] = Check::dice($notation, \sprintf('the field "%s"', $key));

        return new self($key, FieldKind::Rolled, null, $roll, $pool, $count, false);
    }

    /**
     * The roll of a rolled field, ready for `tilttek/dice`.
     *
     * @throws InvalidRules for a generated field, which has none
     */
    public function pool(): Pool
    {
        return $this->pool ?? throw InvalidRules::because(\sprintf('The field "%s" is drawn from a generator, not rolled.', $this->key));
    }

    /**
     * The name of the generator once its placeholders are replaced by the values of the fields before.
     *
     * @param array<string, mixed> $values the values of the fields rolled so far, by key
     *
     * @throws InvalidRules for a rolled field
     * @throws RollFailed   when a value cannot stand in a generator's name
     */
    public function generatorFor(array $values): string
    {
        $generator = $this->generator ?? throw InvalidRules::because(\sprintf('The field "%s" is rolled, not drawn from a generator.', $this->key));

        $name = (string) preg_replace_callback(self::PLACEHOLDER, function (array $match) use ($values): string {
            $value = $values[$match[1]] ?? null;

            if (isset($match[2])) {
                $value = \is_array($value) ? $value[$match[2]] ?? null : null;
            }

            if (\is_int($value)) {
                $value = (string) $value;
            }

            if (!\is_string($value)) {
                throw RollFailed::because(\sprintf('The field "%s" names its generator by "%s", which gave no text.', $this->key, $match[0]));
            }

            return $value;
        }, $generator);

        if (!Reader::isName($name)) {
            throw RollFailed::because(\sprintf('The field "%s" names the generator "%s", which is not a name of a generator.', $this->key, $name));
        }

        return $name;
    }

    /**
     * @return list<string>
     *
     * @throws InvalidRules
     */
    private function references(string $generator): array
    {
        if (\strlen($generator) > self::GENERATOR_LENGTH) {
            throw InvalidRules::because(\sprintf('The generator of the field "%s" is named in at most %d bytes.', $this->key, self::GENERATOR_LENGTH));
        }

        preg_match_all(self::PLACEHOLDER, $generator, $matches);
        $plain = (string) preg_replace(self::PLACEHOLDER, 'x', $generator);

        if (!Reader::isName($plain)) {
            throw InvalidRules::because(\sprintf('The field "%s" names the generator "%s": letters, digits, "_" and "-" in parts joined by dots, and placeholders such as "{ancestry}".', $this->key, $generator));
        }

        return array_values(array_unique($matches[1]));
    }
}
