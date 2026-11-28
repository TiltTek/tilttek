<?php

declare(strict_types=1);

namespace Tilttek\Factions\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\Factions\Exception\InvalidRoster;
use Tilttek\Factions\Internal\Identifier;
use Tilttek\Factions\Subject;

#[CoversClass(Subject::class)]
#[CoversClass(Identifier::class)]
#[CoversClass(InvalidRoster::class)]
final class SubjectTest extends TestCase
{
    public function testThePartyHasNoCharacter(): void
    {
        $party = Subject::party();

        self::assertNull($party->character);
        self::assertSame('party', $party->key);
        self::assertTrue($party->isParty());
    }

    public function testACharacterIsKeyedByTheirIdentifier(): void
    {
        $character = Subject::character('char-1');

        self::assertSame('char-1', $character->character);
        self::assertSame('character:char-1', $character->key);
        self::assertFalse($character->isParty());
    }

    public function testASubjectIsReadBackFromItsKey(): void
    {
        self::assertTrue(Subject::fromKey('party')->isParty());
        self::assertSame('char-1', Subject::fromKey('character:char-1')->character);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function badKeys(): iterable
    {
        yield 'empty' => [''];
        yield 'another word' => ['group'];
        yield 'a prefix inside' => ['xcharacter:char-1'];
        yield 'no identifier' => ['character:'];
        yield 'an address' => ['character:a@b'];
        yield 'a digit first' => ['character:1a'];
    }

    #[DataProvider('badKeys')]
    public function testAKeyOfAnotherShapeIsRefused(string $key): void
    {
        $this->expectException(InvalidRoster::class);

        Subject::fromKey($key);
    }

    public function testAKeyOfAnotherShapeSaysWhichShapesItTakes(): void
    {
        $this->expectException(InvalidRoster::class);
        $this->expectExceptionMessageIsOrContains('The key of a subject is "party", or "character:" and an identifier.');

        Subject::fromKey('group');
    }

    public function testAnIdentifierOfAnotherShapeIsRefused(): void
    {
        $this->expectException(InvalidRoster::class);
        $this->expectExceptionMessageIsOrContains('The identifier of a character is 1 to 128 letters, digits, "_", ".", ":" or "-", starting with a letter.');

        Subject::character('char 1');
    }

    public function testIdentifiersAreBoundedAndAnchored(): void
    {
        self::assertSame(str_repeat('a', 128), Subject::character(str_repeat('a', 128))->character);
        self::assertSame('a', Subject::character('a')->character);
        self::assertSame('a1_.:-', Subject::character('a1_.:-')->character);

        foreach ([str_repeat('a', 129), "a\n", 'a b'] as $bad) {
            try {
                Subject::character($bad);
                self::fail('Refused: '.$bad);
            } catch (InvalidRoster) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testSubjectsAreEqualByTheirKey(): void
    {
        self::assertTrue(Subject::party()->equals(Subject::party()));
        self::assertTrue(Subject::character('a')->equals(Subject::character('a')));
        self::assertFalse(Subject::character('a')->equals(Subject::character('b')));
        self::assertFalse(Subject::character('a')->equals(Subject::party()));
    }
}
