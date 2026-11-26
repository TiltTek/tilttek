<?php

declare(strict_types=1);

namespace Tilttek\Tournament\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\Tournament\Exception\InvalidTournament;
use Tilttek\Tournament\Identifier;

#[CoversClass(Identifier::class)]
final class IdentifierTest extends TestCase
{
    public function testAnIdentifierIsReturnedAsGiven(): void
    {
        self::assertSame('p-1042', Identifier::check('p-1042', 'participant'));
        self::assertSame('a'.str_repeat('b', 127), Identifier::check('a'.str_repeat('b', 127), 'participant'));
        self::assertSame('Ab.c:d@e_f', Identifier::check('Ab.c:d@e_f', 'participant'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function badIdentifiers(): iterable
    {
        yield 'empty' => [''];
        yield 'a digit first' => ['1042'];
        yield 'a space' => ['p 1'];
        yield 'too long' => ['a'.str_repeat('b', 128)];
        yield 'a line break after' => ["p1\n"];
        yield 'a slash' => ['p/1'];
    }

    #[DataProvider('badIdentifiers')]
    public function testAnIdentifierStartsWithALetterAndIsShort(string $value): void
    {
        $this->expectException(InvalidTournament::class);
        $this->expectExceptionMessageIs('The identifier of a participant is 1 to 128 letters, digits, "_", ".", ":", "@" or "-", starting with a letter.');
        Identifier::check($value, 'participant');
    }
}
