<?php

declare(strict_types=1);

namespace Tilttek\RandomCharacter\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\Dice\DiceRoller;
use Tilttek\RandomCharacter\Exception\InvalidRules;
use Tilttek\RandomCharacter\Exception\RollFailed;
use Tilttek\RandomCharacter\Field;
use Tilttek\RandomCharacter\FieldKind;
use Tilttek\RandomCharacter\Internal\Check;

#[CoversClass(Field::class)]
#[CoversClass(FieldKind::class)]
#[CoversClass(Check::class)]
#[CoversClass(InvalidRules::class)]
#[CoversClass(RollFailed::class)]
final class FieldTest extends TestCase
{
    public function testAGeneratedField(): void
    {
        $field = Field::generated('name', 'names.{race}.{race}.{kin.key}-{age}', 3, true);

        self::assertSame('name', $field->key);
        self::assertSame(FieldKind::Generated, $field->kind);
        self::assertSame('names.{race}.{race}.{kin.key}-{age}', $field->generator);
        self::assertNull($field->roll);
        self::assertSame(3, $field->count);
        self::assertTrue($field->unique);
        self::assertSame(['race', 'kin', 'age'], $field->references);
        self::assertSame('names.elf.elf.sylvan-27', $field->generatorFor(['race' => 'elf', 'kin' => ['key' => 'sylvan'], 'age' => 27, 'other' => 'x']));
    }

    public function testAGeneratedFieldIsOneValueByDefault(): void
    {
        $field = Field::generated('name', 'names');

        self::assertSame(1, $field->count);
        self::assertFalse($field->unique);
        self::assertSame([], $field->references);
        self::assertSame('names', $field->generatorFor([]));
    }

    public function testAPlaceholderReadsAValueOfAStructuredResult(): void
    {
        $field = Field::generated('name', 'names.{kin.key}');

        self::assertSame('names.elf', $field->generatorFor(['kin' => ['key' => 'elf', 'label' => 'Elf']]));
    }

    public function testAPlaceholderReadsANumber(): void
    {
        self::assertSame('names.3', Field::generated('name', 'names.{age}')->generatorFor(['age' => 3]));
    }

    public function testARolledField(): void
    {
        $field = Field::rolled('age', '2D10+14', 2);

        self::assertSame(FieldKind::Rolled, $field->kind);
        self::assertSame('2d10+14', $field->roll);
        self::assertNull($field->generator);
        self::assertSame([], $field->references);
        self::assertSame(2, $field->count);
        self::assertFalse($field->unique);
        self::assertSame('2d10+14', new DiceRoller()->write($field->pool()));
    }

    public function testARolledFieldHasNoGenerator(): void
    {
        $this->expectExceptionObject(InvalidRules::because('The field "age" is rolled, not drawn from a generator.'));

        Field::rolled('age', '1d6')->generatorFor([]);
    }

    public function testAGeneratedFieldHasNoPool(): void
    {
        $this->expectExceptionObject(InvalidRules::because('The field "name" is drawn from a generator, not rolled.'));

        Field::generated('name', 'names')->pool();
    }

    public function testTheBoundsAreTaken(): void
    {
        self::assertSame(Field::MAX_COUNT, Field::generated('a', 'x', Field::MAX_COUNT)->count);
        self::assertSame(Field::MAX_COUNT, Field::rolled('a', '1d6', Field::MAX_COUNT)->count);
        self::assertSame(str_repeat('a', Field::GENERATOR_LENGTH), Field::generated('a', str_repeat('a', Field::GENERATOR_LENGTH))->generator);
        self::assertSame('a'.str_repeat('b', 63), Field::generated('a'.str_repeat('b', 63), 'x')->key);
    }

    /**
     * @return iterable<string, array{callable(): Field, non-empty-string}>
     */
    public static function refused(): iterable
    {
        yield 'no value' => [static fn (): Field => Field::generated('name', 'x', 0), 'The field "name" gives 1 to 16 values.'];
        yield 'too many values' => [static fn (): Field => Field::rolled('age', '1d6', 17), 'The field "age" gives 1 to 16 values.'];
        yield 'an upper-case key' => [static fn (): Field => Field::generated('Name', 'x'), 'The key of the field "Name" must be a lowercase letter, then up to 63 lowercase letters, digits or "_".'];
        yield 'a key with a dot' => [static fn (): Field => Field::generated('a.b', 'x'), 'The key of the field "a.b" must be'];
        yield 'a key starting with a digit' => [static fn (): Field => Field::generated('1a', 'x'), 'The key of the field "1a" must be'];
        yield 'a key too long' => [static fn (): Field => Field::generated('a'.str_repeat('b', 64), 'x'), 'The key of the field "a'];
        yield 'a key with a new line' => [static fn (): Field => Field::generated("a\n", 'x'), 'The key of the field "a'];
        yield 'a generator too long' => [static fn (): Field => Field::generated('name', str_repeat('a', 257)), 'The generator of the field "name" is named in at most 256 bytes.'];
        yield 'a wrong generator' => [static fn (): Field => Field::generated('name', 'names..elf'), 'The field "name" names the generator "names..elf": letters, digits, "_" and "-" in parts joined by dots, and placeholders such as "{ancestry}".'];
        yield 'an empty generator' => [static fn (): Field => Field::generated('name', ''), 'The field "name" names the generator ""'];
        yield 'a wrong placeholder' => [static fn (): Field => Field::generated('name', 'names.{Kin}'), 'The field "name" names the generator "names.{Kin}"'];
        yield 'an open placeholder' => [static fn (): Field => Field::generated('name', 'names.{kin'), 'The field "name" names the generator "names.{kin"'];
        yield 'a wrong roll' => [static fn (): Field => Field::rolled('age', 'd'), 'The roll "d" of the field "age" cannot be read: '];
    }

    /**
     * @param callable(): Field $build
     * @param non-empty-string  $message
     */
    #[DataProvider('refused')]
    public function testRefusesWrongFields(callable $build, string $message): void
    {
        try {
            $build();
            self::fail('The field is refused.');
        } catch (InvalidRules $invalidRules) {
            self::assertStringStartsWith($message, $invalidRules->getMessage());
        }
    }

    /**
     * @return iterable<string, array{string, array<string, mixed>, non-empty-string}>
     */
    public static function unusable(): iterable
    {
        yield 'a missing value' => ['names.{kin}', [], 'The field "name" names its generator by "{kin}", which gave no text.'];
        yield 'a list' => ['names.{kin}', ['kin' => ['elf']], 'The field "name" names its generator by "{kin}", which gave no text.'];
        yield 'a missing sub-value' => ['names.{kin.key}', ['kin' => ['label' => 'Elf']], 'The field "name" names its generator by "{kin.key}", which gave no text.'];
        yield 'a sub-value of a text' => ['names.{kin.key}', ['kin' => 'elf'], 'The field "name" names its generator by "{kin.key}", which gave no text.'];
        yield 'a null' => ['names.{kin}', ['kin' => null], 'The field "name" names its generator by "{kin}", which gave no text.'];
        yield 'a text with a space' => ['names.{kin}', ['kin' => 'wood elf'], 'The field "name" names the generator "names.wood elf", which is not a name of a generator.'];
        yield 'an empty text' => ['names.{kin}', ['kin' => ''], 'The field "name" names the generator "names.", which is not a name of a generator.'];
    }

    /**
     * @param array<string, mixed> $values
     */
    #[DataProvider('unusable')]
    public function testRefusesAValueThatCannotNameAGenerator(string $generator, array $values, string $message): void
    {
        $this->expectExceptionObject(RollFailed::because($message));

        Field::generated('name', $generator)->generatorFor($values);
    }

    public function testARolledFieldIsOneRollByDefault(): void
    {
        self::assertSame(1, Field::rolled('age', '1d6')->count);
    }
}
