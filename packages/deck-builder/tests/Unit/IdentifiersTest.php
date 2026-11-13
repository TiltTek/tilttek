<?php

declare(strict_types=1);

namespace Tilttek\DeckBuilder\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\DeckBuilder\Exception\InvalidDeck;
use Tilttek\DeckBuilder\Identifiers;

#[CoversClass(Identifiers::class)]
#[CoversClass(InvalidDeck::class)]
final class IdentifiersTest extends TestCase
{
    public function testCodesOfTheirShapeAreKept(): void
    {
        self::assertSame('a', Identifiers::code('a', 'format'));
        self::assertSame('0-core_set.v2', Identifiers::code('0-core_set.v2', 'format'));
        self::assertSame(str_repeat('a', 64), Identifiers::code(str_repeat('a', 64), 'format'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function refusedCodes(): iterable
    {
        yield 'empty' => [''];
        yield 'upper case' => ['Core'];
        yield 'leading dash' => ['-core'];
        yield 'space' => ['core set'];
        yield 'too long' => [str_repeat('a', 65)];
        yield 'trailing newline' => ["core\n"];
    }

    #[DataProvider('refusedCodes')]
    public function testOtherCodesAreRefused(string $code): void
    {
        $this->expectException(InvalidDeck::class);
        $this->expectExceptionMessageIsOrContains('The code of a format is 1 to 64 lower-case letters, digits, "_", "." or "-", starting with a letter or a digit; "'.substr($code, 0, 64).'" is not.');

        Identifiers::code($code, 'format');
    }

    public function testIdentifiersOfTheirShapeAreKept(): void
    {
        self::assertSame('Card:42/b-1_x.y', Identifiers::id('Card:42/b-1_x.y', 'card'));
        self::assertSame('7', Identifiers::id('7', 'card'));
        self::assertSame(str_repeat('Z', 255), Identifiers::id(str_repeat('Z', 255), 'card'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function refusedIdentifiers(): iterable
    {
        yield 'empty' => [''];
        yield 'at sign' => ['a@b'];
        yield 'leading colon' => [':a'];
        yield 'space' => ['a b'];
        yield 'too long' => [str_repeat('a', 256)];
        yield 'trailing newline' => ["a\n"];
    }

    #[DataProvider('refusedIdentifiers')]
    public function testOtherIdentifiersAreRefused(string $id): void
    {
        $this->expectException(InvalidDeck::class);
        $this->expectExceptionMessageIsOrContains('The identifier of a deck is 1 to 255 letters, digits, "_", ".", ":", "/" or "-", starting with a letter or a digit; "'.substr($id, 0, 64).'" is not.');

        Identifiers::id($id, 'deck');
    }

    public function testNamesOfTextAreKept(): void
    {
        self::assertSame('Été « rouge » 2', Identifiers::name('Été « rouge » 2', 'deck'));
        self::assertSame(str_repeat('é', 200), Identifiers::name(str_repeat('é', 200), 'deck'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function refusedNames(): iterable
    {
        yield 'empty' => [''];
        yield 'leading space' => [' a'];
        yield 'trailing space' => ['a '];
        yield 'newline' => ["a\nb"];
        yield 'not UTF-8' => ["\xFF"];
    }

    #[DataProvider('refusedNames')]
    public function testNamesWithoutTextAreRefused(string $name): void
    {
        $this->expectException(InvalidDeck::class);
        $this->expectExceptionMessageIsOrContains('The name of a card is text without control characters or surrounding spaces.');

        Identifiers::name($name, 'card');
    }

    public function testNamesLongerThan200CharactersAreRefused(): void
    {
        $this->expectException(InvalidDeck::class);
        $this->expectExceptionMessageIsOrContains('The name of a deck has at most 200 characters.');

        Identifiers::name(str_repeat('é', 201), 'deck');
    }

    public function testAKeyOfDigitsIsGivenBackAsAString(): void
    {
        self::assertSame('7', Identifiers::key(7));
        self::assertSame('a', Identifiers::key('a'));
    }
}
