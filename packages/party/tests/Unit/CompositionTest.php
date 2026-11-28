<?php

declare(strict_types=1);

namespace Tilttek\Party\Tests\Unit;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\Party\Composition\Composition;
use Tilttek\Party\Composition\CompositionAdvisor;
use Tilttek\Party\Composition\Coverage;
use Tilttek\Party\Composition\Role;
use Tilttek\Party\Composition\RoleCatalogue;
use Tilttek\Party\Composition\RoleCoverage;
use Tilttek\Party\Exception\InvalidParty;
use Tilttek\Party\Member;
use Tilttek\Party\Standing;
use Tilttek\Party\Text;
use Tilttek\RpgSystems\GameSystem;
use Tilttek\RpgSystems\Licence;
use Tilttek\RpgSystems\PartyRole;
use Tilttek\RpgSystems\Provenance;
use Tilttek\RpgSystems\Term;
use Tilttek\RpgSystems\Translations;

#[CoversClass(Role::class)]
#[CoversClass(Text::class)]
#[CoversClass(RoleCatalogue::class)]
#[CoversClass(CompositionAdvisor::class)]
#[CoversClass(Composition::class)]
#[CoversClass(RoleCoverage::class)]
final class CompositionTest extends TestCase
{
    private CompositionAdvisor $advisor;

    private RoleCatalogue $roles;

    protected function setUp(): void
    {
        $this->advisor = new CompositionAdvisor();
        $this->roles = new RoleCatalogue([new Role('healer', 'Healer'), new Role('tank', 'Tank'), new Role('damage', 'Damage')]);
    }

    public function testARoleKeepsItsKeyNameAndMinimum(): void
    {
        $role = new Role('role.healer', 'Healer');
        self::assertSame(['role.healer', 'Healer', 1], [$role->key, $role->name, $role->minimum]);
        self::assertSame(100, mb_strlen(new Role('scout', str_repeat('a', 100))->name));
        self::assertSame(0, new Role('scout', 'Scout', 0)->minimum);
        self::assertSame(10, new Role('scout', 'Scout', 10)->minimum);
    }

    public function testARoleMinimumIsBounded(): void
    {
        foreach ([-1, 11] as $minimum) {
            try {
                new Role('scout', 'Scout', $minimum);
                self::fail('The minimum was kept.');
            } catch (InvalidParty $invalidParty) {
                self::assertSame(\sprintf('The minimum of a role is 0 to 10; %d is not.', $minimum), $invalidParty->getMessage());
            }
        }
    }

    public function testARoleNameIsAtMostOneHundredCharacters(): void
    {
        $this->expectException(InvalidParty::class);

        new Role('scout', str_repeat('a', 101));
    }

    public function testARoleKeyAndNameAreChecked(): void
    {
        $this->expectException(InvalidParty::class);
        $this->expectExceptionMessageIsOrContains('A role name is 1 to 100 characters');

        new Role('scout', '');
    }

    public function testARoleKeyIsChecked(): void
    {
        $this->expectException(InvalidParty::class);
        $this->expectExceptionMessageIsOrContains('The key of a role must be');

        new Role('Scout', 'Scout');
    }

    public function testACatalogueKeepsItsRolesInOrder(): void
    {
        self::assertSame(['healer', 'tank', 'damage'], array_map(static fn (Role $role): string => $role->key, $this->roles->roles()));
        self::assertTrue($this->roles->has('tank'));
        self::assertFalse($this->roles->has('scout'));
        self::assertSame('Tank', $this->roles->role('tank')?->name);
        self::assertNull($this->roles->role('scout'));
    }

    public function testACatalogueRefusesTwoRolesUnderOneKey(): void
    {
        $this->expectException(InvalidParty::class);
        $this->expectExceptionMessageIsOrContains('Two roles share the key "tank".');

        new RoleCatalogue([new Role('tank', 'Tank'), new Role('tank', 'Defender')]);
    }

    public function testACatalogueHoldsZeroToFiftyRoles(): void
    {
        $fifty = array_map(static fn (int $i): Role => new Role('r'.$i, 'Role '.$i), range(1, 50));
        self::assertCount(50, new RoleCatalogue($fifty)->roles());
        self::assertFalse(new RoleCatalogue($fifty)->isEmpty());
        self::assertFalse(new RoleCatalogue([new Role('scout', 'Scout')])->isEmpty());

        $empty = new RoleCatalogue([]);
        self::assertSame([], $empty->roles());
        self::assertTrue($empty->isEmpty());
        self::assertTrue(RoleCatalogue::none()->isEmpty());
        self::assertSame([], RoleCatalogue::none()->roles());

        $this->expectException(InvalidParty::class);
        $this->expectExceptionMessageIsOrContains('A role catalogue holds at most 50 roles; it holds 51.');

        new RoleCatalogue([...$fifty, new Role('r51', 'Role 51')]);
    }

    public function testACatalogueIsReadFromThePartyRolesOfASystem(): void
    {
        $catalogue = RoleCatalogue::fromSystem($this->system(), locale: 'fr');

        self::assertSame(['healer', 'tank'], array_map(static fn (Role $role): string => $role->key, $catalogue->roles()));
        self::assertSame(['Soigneur', 'Tank'], array_map(static fn (Role $role): string => $role->name, $catalogue->roles()));
        self::assertSame([1, 1], array_map(static fn (Role $role): int => $role->minimum, $catalogue->roles()));

        $english = RoleCatalogue::fromSystem($this->system(), 'en', 0);
        self::assertSame(['Healer', 'Tank'], array_map(static fn (Role $role): string => $role->name, $english->roles()));
        self::assertSame([0, 0], array_map(static fn (Role $role): int => $role->minimum, $english->roles()));
    }

    public function testASystemWithoutRolesGivesAnEmptyCatalogue(): void
    {
        $source = new Provenance('Lanterns', Licence::Owned);
        $system = new GameSystem('lanterns', Translations::of(['en' => 'Lanterns']), $source, null, [], [], [
            new Term('role.healer', Translations::of(['en' => 'Healer']), $source, 'party-role'),
        ]);

        $catalogue = RoleCatalogue::fromSystem($system);

        self::assertTrue($catalogue->isEmpty());
        self::assertSame([], $catalogue->roles());
        self::assertFalse($catalogue->has('role.healer'));
    }

    public function testAGameWithoutRolesGivesNoAdvice(): void
    {
        $composition = $this->advisor->advise([$this->member('1', []), $this->member('2', ['healer', 'warden']), $this->member('3', ['healer'], Standing::Away)], RoleCatalogue::none());

        self::assertFalse($composition->hasAdvice());
        self::assertTrue($composition->isBalanced());
        self::assertSame([], $composition->coverages);
        self::assertSame([], $composition->missing());
        self::assertSame([], $composition->crowded());
        self::assertSame([], $composition->unassigned);
        self::assertSame([], $composition->unknownRoles);
        self::assertNull($composition->coverageOf('healer'));
        self::assertSame(2, $composition->active);
        self::assertSame(0, $composition->fairShare);
    }

    public function testACompositionAgainstRolesHasAdvice(): void
    {
        self::assertTrue($this->advisor->advise([], $this->roles)->hasAdvice());
        self::assertTrue($this->advisor->advise([$this->member('1', ['healer'])], $this->roles)->hasAdvice());
    }

    public function testABalancedPartyFillsEveryRoleOnce(): void
    {
        $composition = $this->advisor->advise([
            $this->member('1', ['healer']),
            $this->member('2', ['tank']),
            $this->member('3', ['damage']),
        ], $this->roles);

        self::assertTrue($composition->isBalanced());
        self::assertSame([], $composition->missing());
        self::assertSame([], $composition->crowded());
        self::assertSame([3, 1], [$composition->active, $composition->fairShare]);
        self::assertSame([Coverage::Covered, Coverage::Covered, Coverage::Covered], array_map(static fn (RoleCoverage $coverage): Coverage => $coverage->coverage, $composition->coverages));
        self::assertSame(['character:2'], array_map(static fn (ResourceReference $member): string => $member->toString(), $composition->coverageOf('tank')->members ?? []));
        self::assertNull($composition->coverageOf('scout'));
    }

    public function testMissingAndCrowdedRolesAreTold(): void
    {
        $composition = $this->advisor->advise([
            $this->member('1', ['healer']),
            $this->member('2', ['healer']),
            $this->member('3', ['healer', 'damage']),
            $this->member('4', ['damage']),
        ], $this->roles);

        self::assertFalse($composition->isBalanced());
        self::assertSame(2, $composition->fairShare);
        self::assertSame(['tank'], array_map(static fn (RoleCoverage $coverage): string => $coverage->role->key, $composition->missing()));
        self::assertSame(['healer'], array_map(static fn (RoleCoverage $coverage): string => $coverage->role->key, $composition->crowded()));
        self::assertSame(3, $composition->coverageOf('healer')?->count());
        self::assertSame(Coverage::Covered, $composition->coverageOf('damage')?->coverage);
    }

    public function testAPartyWithOnlyMissingRolesIsNotBalanced(): void
    {
        $composition = $this->advisor->advise([$this->member('1', ['healer']), $this->member('2', ['damage'])], $this->roles);

        self::assertFalse($composition->isBalanced());
        self::assertSame([], $composition->crowded());
    }

    public function testAPartyWithOnlyCrowdedRolesIsNotBalanced(): void
    {
        $roles = new RoleCatalogue([new Role('healer', 'Healer', 0), new Role('tank', 'Tank', 0)]);
        $composition = $this->advisor->advise([$this->member('1', ['healer']), $this->member('2', ['healer'])], $roles);

        self::assertSame([], $composition->missing());
        self::assertSame(['healer'], array_map(static fn (RoleCoverage $coverage): string => $coverage->role->key, $composition->crowded()));
        self::assertFalse($composition->isBalanced());
    }

    public function testAwayMembersCountNowhere(): void
    {
        $composition = $this->advisor->advise([
            $this->member('2', ['tank'], Standing::Away),
            $this->member('1', ['healer']),
            $this->member('3', [], Standing::Away),
            $this->member('4', ['scout'], Standing::Away),
        ], $this->roles);

        self::assertSame(1, $composition->active);
        self::assertSame(['tank', 'damage'], array_map(static fn (RoleCoverage $coverage): string => $coverage->role->key, $composition->missing()));
        self::assertSame([], $composition->unassigned);
        self::assertSame([], $composition->unknownRoles);
    }

    public function testMembersWithoutAKnownRoleAndUnknownRolesAreTold(): void
    {
        $composition = $this->advisor->advise([
            $this->member('1', []),
            $this->member('2', ['scout', 'bard']),
            $this->member('3', ['scout', 'tank']),
        ], $this->roles);

        self::assertSame(['character:1', 'character:2'], array_map(static fn (ResourceReference $member): string => $member->toString(), $composition->unassigned));
        self::assertSame(['scout' => ['character:2', 'character:3'], 'bard' => ['character:2']], array_map(static fn (array $members): array => array_map(static fn (ResourceReference $member): string => $member->toString(), $members), $composition->unknownRoles));
        self::assertSame(['character:3'], array_map(static fn (ResourceReference $member): string => $member->toString(), $composition->coverageOf('tank')->members ?? []));
    }

    public function testTheFairShareRoundsUpAndNeverFallsBelowTheMinimum(): void
    {
        $roles = new RoleCatalogue([new Role('healer', 'Healer', 3), new Role('tank', 'Tank')]);
        $composition = $this->advisor->advise([
            $this->member('1', ['healer']),
            $this->member('2', ['healer']),
            $this->member('3', ['healer']),
            $this->member('4', ['tank']),
            $this->member('5', ['tank']),
        ], $roles);

        self::assertSame(3, $composition->fairShare);
        self::assertSame(Coverage::Covered, $composition->coverageOf('healer')?->coverage);
        self::assertSame(Coverage::Covered, $composition->coverageOf('tank')?->coverage);

        $aboveTheEvenShare = $this->advisor->advise([
            $this->member('1', ['healer']),
            $this->member('2', ['healer']),
            $this->member('3', ['healer']),
            $this->member('4', ['tank']),
        ], $roles);
        self::assertSame(2, $aboveTheEvenShare->fairShare);
        self::assertSame(Coverage::Covered, $aboveTheEvenShare->coverageOf('healer')?->coverage);

        $crowded = $this->advisor->advise([
            $this->member('1', ['healer', 'tank']),
            $this->member('2', ['healer', 'tank']),
            $this->member('3', ['healer', 'tank']),
            $this->member('4', ['healer', 'tank']),
            $this->member('5', ['healer', 'tank']),
        ], $roles);
        self::assertSame([Coverage::Crowded, Coverage::Crowded], array_map(static fn (RoleCoverage $coverage): Coverage => $coverage->coverage, $crowded->coverages));
    }

    public function testAnEmptyPartyMissesEveryRoleWithAMinimum(): void
    {
        $roles = new RoleCatalogue([new Role('healer', 'Healer'), new Role('scout', 'Scout', 0)]);
        $composition = $this->advisor->advise([], $roles);

        self::assertSame([0, 0], [$composition->active, $composition->fairShare]);
        self::assertSame([Coverage::Missing, Coverage::Covered], array_map(static fn (RoleCoverage $coverage): Coverage => $coverage->coverage, $composition->coverages));
    }

    public function testOneRoleIsNeverCrowded(): void
    {
        $composition = $this->advisor->advise([$this->member('1', ['healer']), $this->member('2', ['healer'])], new RoleCatalogue([new Role('healer', 'Healer')]));

        self::assertTrue($composition->isBalanced());
        self::assertSame(2, $composition->fairShare);
    }

    public function testMembersAreReadFromAnyIterable(): void
    {
        $members = (function (): iterable {
            yield $this->member('1', ['healer']);
            yield $this->member('2', ['tank']);
        })();

        self::assertSame(2, $this->advisor->advise($members, $this->roles)->active);
    }

    /**
     * @param list<string> $roles
     */
    private function member(string $id, array $roles, Standing $standing = Standing::Active): Member
    {
        return new Member(ResourceReference::of('party', '7'), ResourceReference::of('character', $id), $roles, $standing, new DateTimeImmutable('2026-11-25 10:00:00'));
    }

    private function system(): GameSystem
    {
        $source = new Provenance('Lanterns', Licence::Owned);

        return new GameSystem('lanterns', Translations::of(['en' => 'Lanterns']), $source, null, [], [], [
            new Term('condition.blinded', Translations::of(['en' => 'Blinded']), $source, 'condition'),
        ], partyRoles: [
            new PartyRole('healer', Translations::of(['en' => 'Healer', 'fr' => 'Soigneur']), $source),
            new PartyRole('tank', Translations::of(['en' => 'Tank']), $source),
        ]);
    }
}
