<?php

declare(strict_types=1);

namespace Tilttek\SessionPrep\Tests\Unit\Internal;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\SessionPrep\Exception\InvalidPreparation;
use Tilttek\SessionPrep\Internal\Text;

#[CoversClass(Text::class)]
#[CoversClass(InvalidPreparation::class)]
final class TextTest extends TestCase
{
    public function testALineIsTrimmedAndKeptUpToItsLengthInCharacters(): void
    {
        self::assertSame('Ève', Text::line('  Ève  ', 3, 'name'));
        self::assertSame(str_repeat('é', 5), Text::line(str_repeat('é', 5), 5, 'name'));
        self::assertSame('', Text::line('   ', 5, 'role', false));
    }

    public function testALineLongerThanItsLengthIsRefused(): void
    {
        $this->expectExceptionObject(InvalidPreparation::because('The name is 1 to 5 characters.'));

        Text::line(str_repeat('é', 6), 5, 'name');
    }

    public function testAnEmptyRequiredLineIsRefused(): void
    {
        $this->expectExceptionObject(InvalidPreparation::because('The name is 1 to 5 characters.'));

        Text::line(' ', 5, 'name');
    }

    public function testAnOptionalTextSaysItMayBeEmpty(): void
    {
        $this->expectExceptionObject(InvalidPreparation::because('The role is 0 to 2 characters.'));

        Text::line('abc', 2, 'role', false);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function forbiddenInALine(): iterable
    {
        yield 'a line break' => ["a\nb"];
        yield 'a tabulation' => ["a\tb"];
        yield 'a null' => ["a\0b"];
        yield 'an override' => ["a\u{202E}b"];
        yield 'an isolate' => ["a\u{2066}b"];
        yield 'the last isolate' => ["a\u{2069}b"];
        yield 'an embedding' => ["a\u{202A}b"];
    }

    #[DataProvider('forbiddenInALine')]
    public function testALineHoldsNoControlCharacterNorOverride(string $value): void
    {
        $this->expectExceptionObject(InvalidPreparation::because('The name holds a control character or a direction override.'));

        Text::line($value, 10, 'name');
    }

    public function testInvalidUtf8IsRefused(): void
    {
        $this->expectExceptionObject(InvalidPreparation::because('The name is not valid UTF-8.'));

        Text::line("a\xC3", 10, 'name');
    }

    public function testABlockKeepsLineBreaksAndTabulationsWithItsLineEndsWrittenAlike(): void
    {
        self::assertSame("one\ntwo\n\tthree\nfour", Text::block(" one\r\ntwo\n\tthree\rfour\n", 100, 'note'));
        self::assertSame('', Text::block('', 10, 'note'));
        self::assertSame("a\nb", Text::block("a\r\nb", 3, 'note'));
    }

    public function testABlockLongerThanItsLengthIsRefused(): void
    {
        $this->expectExceptionObject(InvalidPreparation::because('The note is 0 to 2 characters.'));

        Text::block('abc', 2, 'note');
    }

    public function testAnEmptyRequiredBlockIsRefused(): void
    {
        $this->expectExceptionObject(InvalidPreparation::because('The note is 1 to 5 characters.'));

        Text::block("\n", 5, 'note', true);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function forbiddenInABlock(): iterable
    {
        yield 'a null' => ["a\0b"];
        yield 'an escape' => ["a\x1Bb"];
        yield 'a vertical tabulation' => ["a\x0Bb"];
        yield 'an override' => ["a\u{202D}b"];
        yield 'an isolate' => ["a\u{2068}b"];
    }

    #[DataProvider('forbiddenInABlock')]
    public function testABlockHoldsNoOtherControlCharacterNorOverride(string $value): void
    {
        $this->expectExceptionObject(InvalidPreparation::because('The note holds a control character or a direction override.'));

        Text::block($value, 10, 'note');
    }

    public function testABlockOfInvalidUtf8IsRefused(): void
    {
        $this->expectExceptionObject(InvalidPreparation::because('The note is not valid UTF-8.'));

        Text::block("\xFF", 10, 'note');
    }
}
