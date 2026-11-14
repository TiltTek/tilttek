<?php

declare(strict_types=1);

namespace Tilttek\Quiz\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\Quiz\Exception\InvalidQuiz;
use Tilttek\Quiz\Pin;

#[CoversClass(Pin::class)]
final class PinTest extends TestCase
{
    public function testAPinIsReadAsAPlayerTypesIt(): void
    {
        self::assertSame('123456', Pin::parse('123456'));
        self::assertSame('012345', Pin::parse(' 012 345 '));
        self::assertSame('123456', Pin::parse('123-456'));
        self::assertSame('123456', Pin::parse('1 2-3 4-5 6'));
        self::assertSame('000000', Pin::parse("\t000000\n"));
        self::assertSame('123456', Pin::parse('   123 - 456    '));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function notPins(): iterable
    {
        yield 'five digits' => ['12345', 'A PIN is six digits, which spaces or hyphens may split.'];
        yield 'seven digits' => ['1234567', 'A PIN is six digits, which spaces or hyphens may split.'];
        yield 'a letter' => ['12a456', 'A PIN is six digits, which spaces or hyphens may split.'];
        yield 'empty' => ['', 'A PIN is six digits, which spaces or hyphens may split.'];
        yield 'another separator' => ['123.456', 'A PIN is six digits, which spaces or hyphens may split.'];
        yield 'a digit of another script' => ['12345٦', 'A PIN is six digits, which spaces or hyphens may split.'];
        yield 'a newline at the end of the digits' => ["123456\n7", 'A PIN is six digits, which spaces or hyphens may split.'];
        yield 'too long' => [str_repeat(' ', 11).'123456', 'A PIN is six digits; at most 16 bytes are read.'];
    }

    #[DataProvider('notPins')]
    public function testWhatIsNotAPinIsRefused(string $input, string $message): void
    {
        $this->expectException(InvalidQuiz::class);
        $this->expectExceptionMessageIsOrContains($message);
        Pin::parse($input);
    }

    public function testSixteenBytesAreRead(): void
    {
        self::assertSame('123456', Pin::parse(str_repeat(' ', 10).'123456'));
    }
}
