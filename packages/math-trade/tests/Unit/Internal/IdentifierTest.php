<?php

declare(strict_types=1);

namespace Tilttek\MathTrade\Tests\Unit\Internal;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\MathTrade\Exception\InvalidTradeList;
use Tilttek\MathTrade\Internal\Identifier;

#[CoversClass(Identifier::class)]
final class IdentifierTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function valid(): iterable
    {
        yield 'one byte' => ['a'];
        yield 'digits' => ['42'];
        yield 'a hundred bytes' => [str_repeat('a', 100)];
        yield 'accents and spaces' => ['Les Aventuriers du Rail — édition 2'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalid(): iterable
    {
        yield 'empty' => [''];
        yield 'a hundred and one bytes' => [str_repeat('a', 101)];
        yield 'fifty-one two-byte letters' => [str_repeat('é', 51)];
        yield 'a line break' => ["a\nb"];
        yield 'a final line break' => ["ab\n"];
        yield 'a tab' => ["\ta"];
        yield 'a NUL byte' => ["a\0"];
        yield 'not UTF-8' => ["\xff"];
    }

    #[DataProvider('valid')]
    public function testAcceptsAnIdentifier(string $value): void
    {
        self::assertTrue(Identifier::isValid($value));
        Identifier::check($value, 'a participant');
    }

    #[DataProvider('invalid')]
    public function testRefusesAnIdentifier(string $value): void
    {
        self::assertFalse(Identifier::isValid($value));
        $this->expectException(InvalidTradeList::class);
        $this->expectExceptionMessageIsOrContains('The identifier of a participant is 1 to 100 bytes of UTF-8 without a control character.');

        Identifier::check($value, 'a participant');
    }

    public function testTakesALongerBound(): void
    {
        self::assertTrue(Identifier::isValid(str_repeat('a', 256), 256));
        self::assertFalse(Identifier::isValid(str_repeat('a', 257), 256));
    }
}
