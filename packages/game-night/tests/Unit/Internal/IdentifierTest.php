<?php

declare(strict_types=1);

namespace Tilttek\GameNight\Tests\Unit\Internal;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\GameNight\Exception\InvalidNight;
use Tilttek\GameNight\Internal\Identifier;

#[CoversClass(Identifier::class)]
final class IdentifierTest extends TestCase
{
    public function testAnIdentifierIsGivenBack(): void
    {
        self::assertSame('a', Identifier::check('a', 'x'));
        self::assertSame('member-1_x.y:z', Identifier::check('member-1_x.y:z', 'x'));
        self::assertSame(str_repeat('a', 128), Identifier::check(str_repeat('a', 128), 'x'));
        self::assertSame('entry@shop', Identifier::game('entry@shop'));
        self::assertSame(str_repeat('g', 128), Identifier::game(str_repeat('g', 128)));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notIdentifiers(): iterable
    {
        yield 'empty' => [''];
        yield 'digit first' => ['1a'];
        yield 'space' => ['a b'];
        yield 'too long' => [str_repeat('a', 129)];
        yield 'new line' => ["a\n"];
        yield 'address' => ['someone@example.org'];
    }

    #[DataProvider('notIdentifiers')]
    public function testAMemberIsNotAnAddress(string $value): void
    {
        $this->expectException(InvalidNight::class);
        $this->expectExceptionMessageIs('The identifier of a member is 1 to 128 letters, digits, "_", ".", ":" or "-", starting with a letter.');

        Identifier::check($value, 'a member');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notGames(): iterable
    {
        yield 'empty' => [''];
        yield 'digit first' => ['1a'];
        yield 'too long' => [str_repeat('a', 129)];
        yield 'new line' => ["a\n"];
    }

    #[DataProvider('notGames')]
    public function testAGameKeepsTheShapeOfTheCatalogue(string $value): void
    {
        $this->expectException(InvalidNight::class);
        $this->expectExceptionMessageIs('The identifier of a game is 1 to 128 letters, digits, "_", ".", ":", "@" or "-", starting with a letter.');

        Identifier::game($value);
    }
}
