<?php

declare(strict_types=1);

namespace Tilttek\Factions\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\Factions\Exception\InvalidRoster;
use Tilttek\Factions\Internal\Identifier;
use Tilttek\Factions\Internal\Text;
use Tilttek\Factions\Reason;
use Tilttek\Factions\Relation;
use Tilttek\Factions\Stance;

#[CoversClass(Relation::class)]
#[CoversClass(Stance::class)]
#[CoversClass(Reason::class)]
#[CoversClass(Identifier::class)]
#[CoversClass(Text::class)]
#[CoversClass(InvalidRoster::class)]
final class RelationTest extends TestCase
{
    public function testAPairIsKeptLowerKeyFirstWhateverItsOrder(): void
    {
        $relation = new Relation('watch', 'guild', Stance::Hostile, '  Bribes stopped.  ', true);

        self::assertSame('guild', $relation->first);
        self::assertSame('watch', $relation->second);
        self::assertSame(Stance::Hostile, $relation->stance);
        self::assertSame('Bribes stopped.', $relation->note);
        self::assertTrue($relation->secret);
        self::assertSame('guild watch', $relation->key);
        self::assertSame('guild watch', new Relation('guild', 'watch', Stance::Allied)->key);
        self::assertSame('guild watch', Relation::pair('watch', 'guild'));
        self::assertSame('guild watch', Relation::pair('guild', 'watch'));
    }

    public function testANoteMayBeEmptyAndARelationOpen(): void
    {
        $relation = new Relation('a', 'b', Stance::Neutral);

        self::assertSame('', $relation->note);
        self::assertFalse($relation->secret);
        self::assertSame(200, mb_strlen(new Relation('a', 'b', Stance::War, str_repeat('é', 200))->note));
    }

    public function testKeysOfDigitsAndLettersCompareAsStrings(): void
    {
        $relation = new Relation('b10', 'b9', Stance::Rival);

        self::assertSame('b10', $relation->first);
        self::assertSame('b9', $relation->second);
    }

    public function testARelationInvolvesBothItsFactions(): void
    {
        $relation = new Relation('watch', 'guild', Stance::Rival);

        self::assertTrue($relation->involves('watch'));
        self::assertTrue($relation->involves('guild'));
        self::assertFalse($relation->involves('cult'));
    }

    public function testRelationsAreEqualByTheirPairStanceNoteAndSecrecy(): void
    {
        $relation = new Relation('a', 'b', Stance::Rival, 'N');

        self::assertTrue($relation->equals(new Relation('b', 'a', Stance::Rival, 'N')));
        self::assertFalse($relation->equals(new Relation('a', 'c', Stance::Rival, 'N')));
        self::assertFalse($relation->equals(new Relation('a', 'b', Stance::War, 'N')));
        self::assertFalse($relation->equals(new Relation('a', 'b', Stance::Rival, 'M')));
        self::assertFalse($relation->equals(new Relation('a', 'b', Stance::Rival, 'N', true)));
    }

    public function testAFactionHasNoRelationWithItself(): void
    {
        $this->expectException(InvalidRoster::class);
        $this->expectExceptionMessageIsOrContains('The faction a has no relation with itself.');

        new Relation('a', 'a', Stance::Allied);
    }

    public function testKeysAndNotesHaveTheirShape(): void
    {
        foreach ([
            [static fn (): Relation => new Relation('1a', 'b', Stance::Allied), 'The key of a faction is 1 to 64'],
            [static fn (): Relation => new Relation('a', 'b c', Stance::Allied), 'The key of a faction is 1 to 64'],
            [static fn (): Relation => new Relation('a', 'b', Stance::Allied, str_repeat('a', 201)), 'The note of a relation is 0 to 200 characters.'],
            [static fn (): Relation => new Relation('a', 'b', Stance::Allied, "a\nb"), 'The note of a relation holds a control character'],
        ] as [$make, $message]) {
            try {
                $make();
                self::fail('Refused: '.$message);
            } catch (InvalidRoster $refused) {
                self::assertStringStartsWith($message, $refused->getMessage());
            }
        }
    }

    public function testTheStancesRunFromTheClosestToTheMostOpposed(): void
    {
        self::assertSame(['allied', 'friendly', 'neutral', 'rival', 'hostile', 'war'], array_map(static fn (Stance $stance): string => $stance->value, Stance::cases()));
    }

    public function testAReasonHoldsItsTextAndItsSession(): void
    {
        $reason = new Reason('  Saved the heir.  ', 'session-12');

        self::assertSame('Saved the heir.', $reason->text);
        self::assertSame('session-12', $reason->session);
        self::assertNull(new Reason('R')->session);
        self::assertSame(500, mb_strlen(new Reason(str_repeat('é', 500))->text));

        foreach ([
            [static fn (): Reason => new Reason(' '), 'The reason of a change is 1 to 500 characters.'],
            [static fn (): Reason => new Reason(str_repeat('a', 501)), 'The reason of a change is 1 to 500 characters.'],
            [static fn (): Reason => new Reason('R', 'session 12'), 'The identifier of a session is 1 to 128'],
        ] as [$make, $message]) {
            try {
                $make();
                self::fail('Refused: '.$message);
            } catch (InvalidRoster $refused) {
                self::assertStringStartsWith($message, $refused->getMessage());
            }
        }
    }
}
