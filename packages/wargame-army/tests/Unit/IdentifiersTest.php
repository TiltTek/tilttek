<?php

declare(strict_types=1);

namespace Tilttek\WargameArmy\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Throwable;
use Tilttek\WargameArmy\Exception\InvalidArmyList;
use Tilttek\WargameArmy\Exception\InvalidCatalogue;
use Tilttek\WargameArmy\Fault;
use Tilttek\WargameArmy\Identifiers;

#[CoversClass(Identifiers::class)]
#[CoversClass(Fault::class)]
#[CoversClass(InvalidCatalogue::class)]
#[CoversClass(InvalidArmyList::class)]
final class IdentifiersTest extends TestCase
{
    public function testValuesOfTheirShapeAreKept(): void
    {
        self::assertSame('a', Identifiers::code('a', 'unit'));
        self::assertSame('0-long_rifle.v2', Identifiers::code('0-long_rifle.v2', 'unit'));
        self::assertSame(str_repeat('a', 64), Identifiers::code(str_repeat('a', 64), 'unit'));
        self::assertSame('List:42/b-1_x.y', Identifiers::id('List:42/b-1_x.y'));
        self::assertSame(str_repeat('Z', 255), Identifiers::id(str_repeat('Z', 255)));
        self::assertSame('2026.1+b-2_A', Identifiers::version('2026.1+b-2_A'));
        self::assertSame(str_repeat('9', 32), Identifiers::version(str_repeat('9', 32)));
        self::assertSame('Été « rouge »', Identifiers::name('Été « rouge »', 'unit'));
        self::assertSame(str_repeat('é', 200), Identifiers::name(str_repeat('é', 200), 'unit'));
        self::assertSame(5, Identifiers::bounded(5, 5, 5, 'a count'));
    }

    /**
     * @return iterable<string, array{callable(): mixed, class-string, string}>
     */
    public static function refused(): iterable
    {
        yield 'code upper case' => [static fn (): string => Identifiers::code('Unit', 'unit'), InvalidCatalogue::class, 'The code of a unit is 1 to 64 lower-case letters, digits, "_", "." or "-", starting with a letter or a digit; "Unit" is not.'];
        yield 'code too long' => [static fn (): string => Identifiers::code(str_repeat('a', 65), 'unit'), InvalidCatalogue::class, 'The code of a unit is'];
        yield 'code with newline' => [static fn (): string => Identifiers::code("a\n", 'unit'), InvalidCatalogue::class, 'The code of a unit is'];
        yield 'code of a list' => [static fn (): string => Identifiers::code('-a', 'unit', Fault::ArmyList), InvalidArmyList::class, 'The code of a unit is 1 to 64'];
        yield 'empty identifier' => [static fn (): string => Identifiers::id(''), InvalidArmyList::class, 'The identifier of an army list is 1 to 255 letters, digits, "_", ".", ":", "/" or "-", starting with a letter or a digit; "" is not.'];
        yield 'identifier too long' => [static fn (): string => Identifiers::id(str_repeat('a', 256)), InvalidArmyList::class, 'The identifier of an army list is'];
        yield 'identifier with at sign' => [static fn (): string => Identifiers::id('a@b'), InvalidArmyList::class, 'The identifier of an army list is'];
        yield 'version with space' => [static fn (): string => Identifiers::version('1 0'), InvalidCatalogue::class, 'The version of a catalogue is 1 to 32 letters, digits, "_", ".", "+" or "-", starting with a letter or a digit; "1 0" is not.'];
        yield 'version too long' => [static fn (): string => Identifiers::version(str_repeat('1', 33)), InvalidCatalogue::class, 'The version of a catalogue is'];
        yield 'version of a list' => [static fn (): string => Identifiers::version('.1', Fault::ArmyList), InvalidArmyList::class, 'The version of a catalogue is'];
        yield 'empty name' => [static fn (): string => Identifiers::name('', 'unit'), InvalidCatalogue::class, 'The name of a unit is text without control characters or surrounding spaces.'];
        yield 'name with space around' => [static fn (): string => Identifiers::name(' a', 'unit'), InvalidCatalogue::class, 'The name of a unit is text'];
        yield 'name with control' => [static fn (): string => Identifiers::name("a\tb", 'unit'), InvalidCatalogue::class, 'The name of a unit is text'];
        yield 'name too long' => [static fn (): string => Identifiers::name(str_repeat('é', 201), 'unit', Fault::ArmyList), InvalidArmyList::class, 'The name of a unit has at most 200 characters.'];
        yield 'below bound' => [static fn (): int => Identifiers::bounded(4, 5, 9, 'the models of a unit'), InvalidCatalogue::class, 'The models of a unit is from 5 to 9; 4 is not.'];
        yield 'above bound' => [static fn (): int => Identifiers::bounded(10, 5, 9, 'the models of a unit', fault: Fault::ArmyList), InvalidArmyList::class, 'The models of a unit is from 5 to 9; 10 is not.'];
    }

    /**
     * @param callable(): mixed       $call
     * @param class-string<Throwable> $exception
     */
    #[DataProvider('refused')]
    public function testRefusedValuesSayWhy(callable $call, string $exception, string $message): void
    {
        $this->expectException($exception);
        $this->expectExceptionMessageIsOrContains($message);

        $call();
    }

    public function testANounTakesItsArticle(): void
    {
        self::assertSame('a unit', Identifiers::a('unit'));
        self::assertSame('an option', Identifiers::a('option'));
        self::assertSame('an army list', Identifiers::a('army list'));
        self::assertSame('an item', Identifiers::a('item'));
    }

    public function testALongRefusedValueIsCutInTheMessage(): void
    {
        $value = str_repeat('A', 60).'bcdefghij';

        try {
            Identifiers::code($value, 'unit');
            self::fail('A refused code was accepted.');
        } catch (InvalidCatalogue $invalidCatalogue) {
            self::assertSame('The code of a unit is 1 to 64 lower-case letters, digits, "_", "." or "-", starting with a letter or a digit; "'.substr($value, 0, 64).'" is not.', $invalidCatalogue->getMessage());
        }
    }

    public function testTheSubjectIsNamedInTheMessageOfARefusedNumber(): void
    {
        try {
            Identifiers::bounded(0, 1, 5, 'the most of the slot "%s"', 'hero');
            self::fail('A refused number was accepted.');
        } catch (InvalidCatalogue $invalidCatalogue) {
            self::assertSame('The most of the slot "hero" is from 1 to 5; 0 is not.', $invalidCatalogue->getMessage());
        }
    }

    public function testAKeyOfDigitsIsGivenBackAsAString(): void
    {
        self::assertSame('7', Identifiers::key(7));
        self::assertSame('a', Identifiers::key('a'));
    }
}
