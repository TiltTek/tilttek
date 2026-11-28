<?php

declare(strict_types=1);

namespace Tilttek\PlayLog\Tests\Unit\Internal;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\PlayLog\Exception\InvalidPlay;
use Tilttek\PlayLog\Internal\Identifier;
use Tilttek\PlayLog\Internal\Text;

#[CoversClass(Identifier::class)]
#[CoversClass(Text::class)]
final class InternalTest extends TestCase
{
    public function testAnIdentifierIsKept(): void
    {
        $long = 'a'.str_repeat('1', 127);

        self::assertSame('member:4_b.c-d', Identifier::check('member:4_b.c-d', 'a member'));
        self::assertSame($long, Identifier::check($long, 'a member'));
        self::assertSame('game@bgg', Identifier::game('game@bgg'));
        self::assertSame($long, Identifier::game($long));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function badIdentifiers(): iterable
    {
        yield 'empty' => [''];
        yield 'a digit first' => ['1abc'];
        yield 'too long' => ['a'.str_repeat('1', 128)];
        yield 'a space' => ['a b'];
        yield 'a line break after' => ["abc\n"];
    }

    #[DataProvider('badIdentifiers')]
    public function testABadIdentifierIsRefused(string $value): void
    {
        $this->expectException(InvalidPlay::class);
        $this->expectExceptionMessageIsOrContains('The identifier of a club is 1 to 128');

        Identifier::check($value, 'a club');
    }

    #[DataProvider('badIdentifiers')]
    public function testABadGameIsRefused(string $value): void
    {
        $this->expectException(InvalidPlay::class);

        Identifier::game($value);
    }

    public function testAnAddressIsNoMember(): void
    {
        $this->expectException(InvalidPlay::class);

        Identifier::check('a@b', 'a member');
    }

    public function testALineIsTrimmed(): void
    {
        self::assertSame('Café du coin', Text::line("  Café du coin\t ", 12, 'place'));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function badLines(): iterable
    {
        yield 'not UTF-8' => ["caf\xE9", 'The place is not valid UTF-8.'];
        yield 'empty' => ['   ', 'The place is 1 to 12 characters.'];
        yield 'too long' => [str_repeat('é', 13), 'The place is 1 to 12 characters.'];
        yield 'a control character' => ["a\x07b", 'The place holds a control character or a direction override.'];
        yield 'a line break' => ["a\nb", 'The place holds a control character or a direction override.'];
        yield 'an override' => ["a\u{202E}b", 'The place holds a control character or a direction override.'];
        yield 'an isolate' => ["a\u{2068}b", 'The place holds a control character or a direction override.'];
    }

    #[DataProvider('badLines')]
    public function testABadLineIsRefused(string $value, string $message): void
    {
        $this->expectException(InvalidPlay::class);
        $this->expectExceptionMessageIsOrContains($message);

        Text::line($value, 12, 'place');
    }

    public function testTheLengthIsInCharacters(): void
    {
        self::assertSame(str_repeat('é', 12), Text::line(str_repeat('é', 12), 12, 'place'));
    }
}
