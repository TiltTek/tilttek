<?php

declare(strict_types=1);

namespace Tilttek\Factions\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\Factions\Exception\InvalidRoster;
use Tilttek\Factions\Faction;
use Tilttek\Factions\Internal\Identifier;
use Tilttek\Factions\Internal\Text;
use Tilttek\Factions\Membership;
use Tilttek\Factions\Rank;
use Tilttek\Factions\Standing;
use Tilttek\Factions\Subject;

#[CoversClass(Faction::class)]
#[CoversClass(Rank::class)]
#[CoversClass(Membership::class)]
#[CoversClass(Standing::class)]
#[CoversClass(Identifier::class)]
#[CoversClass(Text::class)]
#[CoversClass(InvalidRoster::class)]
final class FactionTest extends TestCase
{
    private function guild(): Faction
    {
        return new Faction(
            'guild',
            "  The Thieves' Guild  ",
            "  Runs the docks.\r\nOwes the duke.\t ",
            [new Rank('cutpurse', 'Cutpurse'), new Rank('fence', 'Fence'), new Rank('master', 'Guildmaster')],
            [new Standing(Subject::party(), 1), new Standing(Subject::character('char-1'), -2)],
            [new Membership('char-1', 'cutpurse')],
        );
    }

    public function testAFactionHoldsItsTrimmedTextsRanksStandingsAndMembers(): void
    {
        $guild = $this->guild();

        self::assertSame('guild', $guild->key);
        self::assertSame("The Thieves' Guild", $guild->name);
        self::assertSame("Runs the docks.\nOwes the duke.", $guild->summary);
        self::assertSame(['cutpurse', 'fence', 'master'], array_map(static fn (Rank $rank): string => $rank->key, $guild->ranks));
        self::assertSame('Guildmaster', $guild->rank('master')?->title);
        self::assertNull($guild->rank('duke'));
        self::assertSame(1, $guild->standing(Subject::party())?->value);
        self::assertSame(-2, $guild->standing(Subject::character('char-1'))?->value);
        self::assertNull($guild->standing(Subject::character('char-2')));
        self::assertSame('cutpurse', $guild->membership('char-1')?->rank);
        self::assertNull($guild->membership('char-2'));
        self::assertFalse($guild->secret);
        self::assertTrue(new Faction('cult', 'Cult', secret: true)->secret);
    }

    public function testAFactionMayHoldNothingButItsName(): void
    {
        $faction = new Faction('f', 'F');

        self::assertSame('', $faction->summary);
        self::assertSame([], $faction->ranks);
        self::assertSame([], $faction->standings);
        self::assertSame([], $faction->members);
    }

    public function testRanksAreOrderedFromTheLowest(): void
    {
        $guild = $this->guild();

        self::assertTrue($guild->outranks('master', 'fence'));
        self::assertTrue($guild->outranks('fence', 'cutpurse'));
        self::assertFalse($guild->outranks('cutpurse', 'fence'));
        self::assertFalse($guild->outranks('fence', 'fence'));
    }

    public function testOutranksNamesARankTheFactionHolds(): void
    {
        $this->expectException(InvalidRoster::class);
        $this->expectExceptionMessageIsOrContains('The faction guild has no rank duke.');

        $this->guild()->outranks('duke', 'fence');
    }

    public function testOutranksChecksTheSecondRankToo(): void
    {
        $this->expectException(InvalidRoster::class);
        $this->expectExceptionMessageIsOrContains('The faction guild has no rank duke.');

        $this->guild()->outranks('fence', 'duke');
    }

    public function testAStandingIsSetInPlaceOrAfterTheOthers(): void
    {
        $guild = $this->guild();
        $changed = $guild->withStanding(Subject::party(), 3)->withStanding(Subject::character('char-2'), 0);

        self::assertSame(['party', 'character:char-1', 'character:char-2'], array_map(static fn (Standing $standing): string => $standing->subject->key, $changed->standings));
        self::assertSame([3, -2, 0], array_map(static fn (Standing $standing): int => $standing->value, $changed->standings));
        self::assertSame(1, $guild->standing(Subject::party())?->value);
        self::assertSame($guild->members, $changed->members);
        self::assertSame($guild->name, $changed->name);
        self::assertSame($guild->summary, $changed->summary);
        self::assertSame($guild->ranks, $changed->ranks);
        self::assertTrue(new Faction('c', 'C', secret: true)->withStanding(Subject::party(), 1)->secret);
    }

    public function testAMemberJoinsRisesAndLeaves(): void
    {
        $guild = $this->guild();
        $joined = $guild->withMember('char-2', 'fence');
        $risen = $joined->withMember('char-1', 'master');
        $left = $risen->withMember('char-1', null);

        self::assertSame(['char-1', 'char-2'], array_map(static fn (Membership $member): string => $member->character, $joined->members));
        self::assertSame(['master', 'fence'], array_map(static fn (Membership $member): string => $member->rank, $risen->members));
        self::assertSame(['char-2'], array_map(static fn (Membership $member): string => $member->character, $left->members));
        self::assertSame([], $left->withMember('char-2', null)->members);
        self::assertSame($guild->standings, $left->standings);
        self::assertSame($guild->members, $guild->withMember('char-9', null)->members);
    }

    public function testAMemberHoldsARankOfTheFaction(): void
    {
        $this->expectException(InvalidRoster::class);
        $this->expectExceptionMessageIsOrContains('The character char-2 holds the rank duke, which the faction guild lacks.');

        $this->guild()->withMember('char-2', 'duke');
    }

    public function testTheDetailsOfAnotherFactionKeepTheStandingsAndMembers(): void
    {
        $guild = $this->guild();
        $other = new Faction('other', 'The Guild', 'Gone legit.', [new Rank('cutpurse', 'Apprentice'), new Rank('boss', 'Boss')], secret: true);
        $changed = $guild->withDetailsOf($other);

        self::assertSame('guild', $changed->key);
        self::assertSame('The Guild', $changed->name);
        self::assertSame('Gone legit.', $changed->summary);
        self::assertSame($other->ranks, $changed->ranks);
        self::assertTrue($changed->secret);
        self::assertSame($guild->standings, $changed->standings);
        self::assertSame($guild->members, $changed->members);
        self::assertTrue($changed->hasDetailsOf($other));
        self::assertFalse($guild->hasDetailsOf($other));
    }

    public function testDetailsDifferByEachOfTheirParts(): void
    {
        $ranks = [new Rank('a', 'A'), new Rank('b', 'B')];
        $faction = new Faction('f', 'F', 'S', $ranks);

        self::assertTrue($faction->hasDetailsOf(new Faction('g', 'F', 'S', $ranks, [new Standing(Subject::party(), 1)])));
        self::assertFalse($faction->hasDetailsOf(new Faction('f', 'G', 'S', $ranks)));
        self::assertFalse($faction->hasDetailsOf(new Faction('f', 'F', 'T', $ranks)));
        self::assertFalse($faction->hasDetailsOf(new Faction('f', 'F', 'S', $ranks, secret: true)));
        self::assertFalse($faction->hasDetailsOf(new Faction('f', 'F', 'S', [new Rank('a', 'A')])));
        self::assertFalse($faction->hasDetailsOf(new Faction('f', 'F', 'S', [new Rank('a', 'A'), new Rank('c', 'B')])));
        self::assertFalse($faction->hasDetailsOf(new Faction('f', 'F', 'S', [new Rank('a', 'A'), new Rank('b', 'C')])));
    }

    public function testDetailsThatDropAHeldRankAreRefused(): void
    {
        $this->expectException(InvalidRoster::class);
        $this->expectExceptionMessageIsOrContains('The character char-1 holds the rank cutpurse, which the faction guild lacks.');

        $this->guild()->withDetailsOf(new Faction('guild', 'Guild'));
    }

    public function testBoundsAreKept(): void
    {
        $ranks = array_map(static fn (int $i): Rank => new Rank('r'.$i, 'R'), range(1, Faction::MAX_RANKS));
        $standings = array_map(static fn (int $i): Standing => new Standing(Subject::character('c'.$i), 0), range(1, Faction::MAX_STANDINGS));
        $members = array_map(static fn (int $i): Membership => new Membership('c'.$i, 'r1'), range(1, Faction::MAX_MEMBERS));
        $full = new Faction('f', str_repeat('é', Faction::MAX_NAME), str_repeat('é', Faction::MAX_SUMMARY), $ranks, $standings, $members);

        self::assertCount(16, $full->ranks);
        self::assertCount(64, $full->standings);
        self::assertCount(64, $full->members);
        self::assertSame(4_000, mb_strlen($full->summary));

        foreach ([
            [static fn (): Faction => new Faction('f', 'F', '', [...$ranks, new Rank('x', 'X')]), 'A faction holds at most 16 ranks.'],
            [static fn (): Faction => new Faction('f', 'F', '', $ranks, [...$standings, new Standing(Subject::party(), 0)]), 'A faction holds a standing with at most 64 subjects.'],
            [static fn (): Faction => new Faction('f', 'F', '', $ranks, [], [...$members, new Membership('x', 'r1')]), 'A faction holds at most 64 members.'],
            [static fn (): Faction => $full->withStanding(Subject::party(), 0), 'A faction holds a standing with at most 64 subjects.'],
            [static fn (): Faction => $full->withMember('x', 'r1'), 'A faction holds at most 64 members.'],
            [static fn (): Faction => new Faction('f', str_repeat('a', 121)), 'The name of a faction is 1 to 120 characters.'],
            [static fn (): Faction => new Faction('f', ' '), 'The name of a faction is 1 to 120 characters.'],
            [static fn (): Faction => new Faction('f', 'F', str_repeat('a', 4_001)), 'The summary of a faction is 0 to 4000 characters.'],
            [static fn (): Faction => new Faction('f', "F\tG"), 'The name of a faction holds a control character or a direction override.'],
            [static fn (): Faction => new Faction('f', 'F', "a\u{202E}b"), 'The summary of a faction holds a control character or a direction override.'],
            [static fn (): Faction => new Faction('f', 'F', "a\x00b"), 'The summary of a faction holds a control character or a direction override.'],
            [static fn (): Faction => new Faction('f', "\xFF"), 'The name of a faction is not valid UTF-8.'],
            [static fn (): Faction => new Faction('9f', 'F'), 'The key of a faction is 1 to 64 letters, digits, "_", ".", ":" or "-", starting with a letter.'],
            [static fn (): Faction => new Faction(str_repeat('f', 65), 'F'), 'The key of a faction is 1 to 64'],
        ] as [$make, $message]) {
            try {
                $make();
                self::fail('Refused: '.$message);
            } catch (InvalidRoster $refused) {
                self::assertStringStartsWith($message, $refused->getMessage());
            }
        }

        self::assertSame(str_repeat('f', 64), new Faction(str_repeat('f', 64), 'F')->key);
        self::assertSame("a\tb", new Faction('f', 'F', "a\tb")->summary);
        self::assertSame("a\nb", new Faction('f', 'F', "a\rb")->summary);
    }

    public function testKeysSubjectsAndCharactersComeOnce(): void
    {
        foreach ([
            [static fn (): Faction => new Faction('f', 'F', '', [new Rank('a', 'A'), new Rank('b', 'B'), new Rank('a', 'C')]), 'The rank a comes twice in the faction f.'],
            [static fn (): Faction => new Faction('f', 'F', '', [], [new Standing(Subject::party(), 0), new Standing(Subject::party(), 1)]), 'The subject party has two standings with the faction f.'],
            [static fn (): Faction => new Faction('f', 'F', '', [new Rank('a', 'A')], [], [new Membership('c', 'a'), new Membership('c', 'a')]), 'The character c is a member of the faction f twice.'],
        ] as [$make, $message]) {
            try {
                $make();
                self::fail('Refused: '.$message);
            } catch (InvalidRoster $refused) {
                self::assertSame($message, $refused->getMessage());
            }
        }
    }

    public function testRanksAndMembershipsHaveTheirShape(): void
    {
        self::assertSame('Guildmaster', new Rank('master', ' Guildmaster ')->title);
        self::assertTrue(new Rank('a', 'A')->equals(new Rank('a', 'A')));
        self::assertFalse(new Rank('a', 'A')->equals(new Rank('b', 'A')));
        self::assertFalse(new Rank('a', 'A')->equals(new Rank('a', 'B')));

        foreach ([
            [static fn (): Rank => new Rank('1a', 'A'), 'The key of a rank is 1 to 64'],
            [static fn (): Rank => new Rank('a', str_repeat('a', 81)), 'The title of a rank is 1 to 80 characters.'],
            [static fn (): Membership => new Membership('c@d', 'a'), 'The identifier of a character is 1 to 128'],
            [static fn (): Membership => new Membership('c', 'a b'), 'The key of a rank is 1 to 64'],
        ] as [$make, $message]) {
            try {
                $make();
                self::fail('Refused: '.$message);
            } catch (InvalidRoster $refused) {
                self::assertStringStartsWith($message, $refused->getMessage());
            }
        }

        self::assertSame(80, mb_strlen(new Rank('a', str_repeat('é', 80))->title));
    }
}
