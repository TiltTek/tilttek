<?php

declare(strict_types=1);

namespace Tilttek\GameCatalog\Tests\Unit\Internal;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\GameCatalog\Exception\InvalidEntry;
use Tilttek\GameCatalog\Internal\Average;
use Tilttek\GameCatalog\Internal\Identifier;
use Tilttek\GameCatalog\Internal\Text;

#[CoversClass(Identifier::class)]
#[CoversClass(Text::class)]
#[CoversClass(Average::class)]
final class InternalTest extends TestCase
{
    public function testAnIdentifierIsReturnedAsGiven(): void
    {
        self::assertSame('e-1042', Identifier::check('e-1042', 'an entry'));
        self::assertSame('a'.str_repeat('b', 127), Identifier::check('a'.str_repeat('b', 127), 'an entry'));
        self::assertSame('Ab.c:d@e_f', Identifier::check('Ab.c:d@e_f', 'an entry'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function badIdentifiers(): iterable
    {
        yield 'empty' => [''];
        yield 'a digit first' => ['1042'];
        yield 'a space' => ['e 1'];
        yield 'too long' => ['a'.str_repeat('b', 128)];
        yield 'a line break after' => ["e1\n"];
        yield 'a slash' => ['e/1'];
    }

    #[DataProvider('badIdentifiers')]
    public function testAnIdentifierStartsWithALetterAndIsShort(string $value): void
    {
        $this->expectException(InvalidEntry::class);
        $this->expectExceptionMessageIs('The identifier of an entry is 1 to 128 letters, digits, "_", ".", ":", "@" or "-", starting with a letter.');
        Identifier::check($value, 'an entry');
    }

    public function testALineIsTrimmedAndCountedInCharacters(): void
    {
        self::assertSame('Héros', Text::line("  Héros\u{0020}", 5, 'name'));
        self::assertSame('a', Text::line('a', 1, 'name'));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function badLines(): iterable
    {
        yield 'empty' => ['', 'The name is 1 to 5 characters.'];
        yield 'spaces only' => ['   ', 'The name is 1 to 5 characters.'];
        yield 'too long' => ['Héross', 'The name is 1 to 5 characters.'];
        yield 'a line break' => ["a\nb", 'The name holds a control character.'];
        yield 'a tab' => ["a\tb", 'The name holds a control character.'];
        yield 'a null byte' => ["a\0b", 'The name holds a control character.'];
        yield 'not UTF-8' => ["a\xFFb", 'The name is not valid UTF-8.'];
    }

    #[DataProvider('badLines')]
    public function testALineIsShortAndWithoutControlCharacters(string $value, string $message): void
    {
        $this->expectException(InvalidEntry::class);
        $this->expectExceptionMessageIs($message);
        Text::line($value, 5, 'name');
    }

    public function testLinesKeepTheirLineBreaks(): void
    {
        self::assertSame("one\ntwo", Text::lines("one\r\ntwo\n", 7, 'text'));
        self::assertSame("one\ntwo", Text::lines("one\ntwo", 7, 'text'));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function badLinesOfText(): iterable
    {
        yield 'a tab' => ["one\ttwo", 'The text holds a control character.'];
        yield 'a carriage return alone' => ["one\rtwo", 'The text holds a control character.'];
        yield 'too long' => ["one\ntwo!", 'The text is 1 to 7 characters.'];
        yield 'not UTF-8' => ["\xC3", 'The text is not valid UTF-8.'];
    }

    #[DataProvider('badLinesOfText')]
    public function testLinesHoldNoOtherControlCharacter(string $value, string $message): void
    {
        $this->expectException(InvalidEntry::class);
        $this->expectExceptionMessageIs($message);
        Text::lines($value, 7, 'text');
    }

    public function testAnAverageIsInHundredthsRoundedHalfUp(): void
    {
        self::assertNull(Average::hundredths(0, 0));
        self::assertSame(400, Average::hundredths(4, 1));
        self::assertSame(350, Average::hundredths(7, 2));
        self::assertSame(333, Average::hundredths(10, 3));
        self::assertSame(367, Average::hundredths(11, 3));
        self::assertSame(113, Average::hundredths(9, 8));
        self::assertSame(112, Average::hundredths(29, 26));
    }
}
