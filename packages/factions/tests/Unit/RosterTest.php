<?php

declare(strict_types=1);

namespace Tilttek\Factions\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\Factions\Exception\InvalidRoster;
use Tilttek\Factions\Faction;
use Tilttek\Factions\Internal\Identifier;
use Tilttek\Factions\Relation;
use Tilttek\Factions\Roster;
use Tilttek\Factions\Scale;
use Tilttek\Factions\Stance;
use Tilttek\Factions\Standing;
use Tilttek\Factions\Subject;
use Tilttek\Factions\Tier;

#[CoversClass(Roster::class)]
#[CoversClass(Identifier::class)]
#[CoversClass(InvalidRoster::class)]
final class RosterTest extends TestCase
{
    private function roster(): Roster
    {
        return new Roster('campaign-1', 'member-1', Scale::standard(), [
            new Faction('guild', 'Guild', standings: [new Standing(Subject::party(), 2)]),
            new Faction('watch', 'Watch'),
            new Faction('cult', 'Cult', secret: true),
        ], [
            new Relation('watch', 'guild', Stance::Hostile),
            new Relation('cult', 'guild', Stance::Allied, secret: true),
        ]);
    }

    public function testARosterHoldsItsFactionsAndRelationsInTheOrderGiven(): void
    {
        $roster = $this->roster();

        self::assertSame('campaign-1', $roster->campaign);
        self::assertSame('member-1', $roster->gameMaster);
        self::assertSame(0, $roster->version);
        self::assertSame(['guild', 'watch', 'cult'], array_map(static fn (Faction $faction): string => $faction->key, $roster->factions));
        self::assertSame(['guild watch', 'cult guild'], array_map(static fn (Relation $relation): string => $relation->key, $roster->relations));
        self::assertSame('Watch', $roster->faction('watch')?->name);
        self::assertNull($roster->faction('duke'));
        self::assertSame('Cult', $roster->get('cult')->name);
        self::assertSame([], new Roster('c', 'm', Scale::standard())->factions);
        self::assertSame([], new Roster('c', 'm', Scale::standard())->relations);
    }

    public function testGetNamesAFactionOfTheRoster(): void
    {
        $this->expectException(InvalidRoster::class);
        $this->expectExceptionMessageIsOrContains('No faction of the roster has the key duke.');

        $this->roster()->get('duke');
    }

    public function testAStandingNotSetIsTheStartOfTheScale(): void
    {
        $roster = $this->roster();

        self::assertSame(2, $roster->standingOf('guild', Subject::party()));
        self::assertSame(0, $roster->standingOf('guild', Subject::character('char-1')));
        self::assertSame(-1, new Roster('c', 'm', new Scale(-3, 3, -1, [new Tier('t', 'T', -3)]), [new Faction('f', 'F')])->standingOf('f', Subject::party()));
    }

    public function testRelationsAreFoundInEitherOrder(): void
    {
        $roster = $this->roster();

        self::assertSame(Stance::Hostile, $roster->relation('guild', 'watch')?->stance);
        self::assertSame(Stance::Hostile, $roster->relation('watch', 'guild')?->stance);
        self::assertNull($roster->relation('watch', 'cult'));
        self::assertSame(['guild watch', 'cult guild'], array_map(static fn (Relation $relation): string => $relation->key, $roster->relationsOf('guild')));
        self::assertSame(['guild watch'], array_map(static fn (Relation $relation): string => $relation->key, $roster->relationsOf('watch')));
        self::assertSame([], $roster->relationsOf('duke'));
        self::assertSame([0, 1], array_keys($roster->relationsOf('guild')));
        self::assertSame([0], array_keys($roster->relationsOf('watch')));
        self::assertSame([0], array_keys($roster->relationsOf('cult')));
    }

    public function testAFactionIsReplacedInPlaceOrAddedAfterTheOthers(): void
    {
        $roster = $this->roster();
        $changed = $roster->withFaction(new Faction('watch', 'The Watch'))->withFaction(new Faction('duke', 'Duke'));

        self::assertSame(['guild', 'watch', 'cult', 'duke'], array_map(static fn (Faction $faction): string => $faction->key, $changed->factions));
        self::assertSame('The Watch', $changed->get('watch')->name);
        self::assertSame($roster->relations, $changed->relations);
        self::assertSame('Watch', $roster->get('watch')->name);
    }

    public function testAFactionLeavesWithItsRelations(): void
    {
        $roster = $this->roster()->withoutFaction('guild');

        self::assertSame(['watch', 'cult'], array_map(static fn (Faction $faction): string => $faction->key, $roster->factions));
        self::assertSame([], $roster->relations);

        $kept = $this->roster()->withoutFaction('cult');
        self::assertSame(['guild watch'], array_map(static fn (Relation $relation): string => $relation->key, $kept->relations));

        $first = new Roster('c', 'm', Scale::standard(), [new Faction('a', 'A'), new Faction('b', 'B'), new Faction('c', 'C')], [new Relation('a', 'b', Stance::Allied), new Relation('b', 'c', Stance::Hostile)]);
        self::assertSame([0], array_keys($first->withoutFaction('a')->relations));
        self::assertSame('b c', $first->withoutFaction('a')->relations[0]->key);
    }

    public function testOnlyAFactionOfTheRosterLeaves(): void
    {
        $this->expectException(InvalidRoster::class);
        $this->expectExceptionMessageIsOrContains('No faction of the roster has the key duke.');

        $this->roster()->withoutFaction('duke');
    }

    public function testARelationIsReplacedInPlaceOrAddedAfterTheOthers(): void
    {
        $roster = $this->roster()
            ->withRelation(new Relation('guild', 'watch', Stance::War))
            ->withRelation(new Relation('watch', 'cult', Stance::Rival));

        self::assertSame(['guild watch', 'cult guild', 'cult watch'], array_map(static fn (Relation $relation): string => $relation->key, $roster->relations));
        self::assertSame(Stance::War, $roster->relation('guild', 'watch')?->stance);
    }

    public function testARelationEndsInEitherOrder(): void
    {
        $roster = $this->roster()->withoutRelation('watch', 'guild');

        self::assertSame(['cult guild'], array_map(static fn (Relation $relation): string => $relation->key, $roster->relations));
    }

    public function testOnlyARelationHeldEnds(): void
    {
        $this->expectException(InvalidRoster::class);
        $this->expectExceptionMessageIsOrContains('The factions watch and cult have no relation.');

        $this->roster()->withoutRelation('watch', 'cult');
    }

    public function testTheScaleTheGameMasterAndTheVersionChangeAlone(): void
    {
        $roster = $this->roster();
        $wider = new Scale(-5, 5, 0, [new Tier('t', 'T', -5)]);

        self::assertSame($wider, $roster->withScale($wider)->scale);
        self::assertSame('member-2', $roster->withGameMaster('member-2')->gameMaster);
        self::assertSame(4, $roster->withVersion(4)->version);
        self::assertSame($roster->factions, $roster->withVersion(4)->factions);
        self::assertSame($roster->relations, $roster->withGameMaster('member-2')->relations);
        self::assertSame($roster->campaign, $roster->withScale($wider)->campaign);
    }

    public function testAStandingOffTheNewScaleIsRefused(): void
    {
        $this->expectException(InvalidRoster::class);
        $this->expectExceptionMessageIsOrContains('The standing 2 of party with the faction guild is off the scale, from -1 to 1.');

        $this->roster()->withScale(new Scale(-1, 1, 0, [new Tier('t', 'T', -1)]));
    }

    public function testAStandingBelowTheScaleIsRefused(): void
    {
        $this->expectException(InvalidRoster::class);
        $this->expectExceptionMessageIsOrContains('The standing -4 of character:c with the faction f is off the scale, from -3 to 3.');

        new Roster('c', 'm', Scale::standard(), [new Faction('f', 'F', standings: [new Standing(Subject::character('c'), -4)])]);
    }

    public function testTheShapeAndTheBoundsOfARosterAreChecked(): void
    {
        $factions = array_map(static fn (int $i): Faction => new Faction('f'.$i, 'F'), range(1, Roster::MAX_FACTIONS));
        $full = new Roster('c', 'm', Scale::standard(), $factions);
        self::assertCount(64, $full->factions);

        $relations = [];
        for ($i = 1; \count($relations) < Roster::MAX_RELATIONS; ++$i) {
            for ($j = $i + 1; $j <= 64 && \count($relations) < Roster::MAX_RELATIONS; ++$j) {
                $relations[] = new Relation('f'.$i, 'f'.$j, Stance::Neutral);
            }
        }

        $related = new Roster('c', 'm', Scale::standard(), $factions, $relations);
        self::assertCount(512, $related->relations);

        foreach ([
            [static fn (): Roster => new Roster('c@x', 'm', Scale::standard()), 'The identifier of a campaign is 1 to 128'],
            [static fn (): Roster => new Roster('c', 'm m', Scale::standard()), 'The identifier of a member is 1 to 128'],
            [static fn (): Roster => new Roster('c', 'm', Scale::standard(), version: -1), 'The version of a roster is 0 or more.'],
            [static fn (): Roster => new Roster('c', 'm', Scale::standard(), [...$factions, new Faction('x', 'X')]), 'A roster holds at most 64 factions.'],
            [static fn (): Roster => $full->withFaction(new Faction('x', 'X')), 'A roster holds at most 64 factions.'],
            [static fn (): Roster => new Roster('c', 'm', Scale::standard(), $factions, [...$relations, new Relation('f63', 'f64', Stance::Neutral)]), 'A roster holds at most 512 relations.'],
            [static fn (): Roster => new Roster('c', 'm', Scale::standard(), [new Faction('a', 'A'), new Faction('b', 'B'), new Faction('a', 'C')]), 'The key a comes twice among the factions.'],
            [static fn (): Roster => new Roster('c', 'm', Scale::standard(), [new Faction('a', 'A')], [new Relation('a', 'b', Stance::War)]), 'The relation between a and b names a faction the roster lacks.'],
            [static fn (): Roster => new Roster('c', 'm', Scale::standard(), [new Faction('b', 'B')], [new Relation('a', 'b', Stance::War)]), 'The relation between a and b names a faction the roster lacks.'],
            [static fn (): Roster => new Roster('c', 'm', Scale::standard(), [new Faction('a', 'A'), new Faction('b', 'B')], [new Relation('a', 'b', Stance::War), new Relation('b', 'a', Stance::Allied)]), 'The factions a and b are related twice.'],
        ] as [$make, $message]) {
            try {
                $make();
                self::fail('Refused: '.$message);
            } catch (InvalidRoster $refused) {
                self::assertStringStartsWith($message, $refused->getMessage());
            }
        }

        self::assertSame(0, new Roster('c', 'm', Scale::standard(), version: 0)->version);
    }
}
