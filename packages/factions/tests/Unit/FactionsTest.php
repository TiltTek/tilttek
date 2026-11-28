<?php

declare(strict_types=1);

namespace Tilttek\Factions\Tests\Unit;

use DateTimeImmutable;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Tilttek\Factions\Event\FactionAdded;
use Tilttek\Factions\Event\FactionChanged;
use Tilttek\Factions\Event\FactionRemoved;
use Tilttek\Factions\Event\RankChanged;
use Tilttek\Factions\Event\RelationChanged;
use Tilttek\Factions\Event\RosterCreated;
use Tilttek\Factions\Event\RosterDeleted;
use Tilttek\Factions\Event\RosterHandedOver;
use Tilttek\Factions\Event\ScaleChanged;
use Tilttek\Factions\Event\StandingChanged;
use Tilttek\Factions\Exception\InvalidRoster;
use Tilttek\Factions\Exception\RosterConflict;
use Tilttek\Factions\Exception\RosterNotFound;
use Tilttek\Factions\Exception\RosterRefused;
use Tilttek\Factions\Faction;
use Tilttek\Factions\Factions;
use Tilttek\Factions\History\Entry;
use Tilttek\Factions\History\RankEntry;
use Tilttek\Factions\History\RelationEntry;
use Tilttek\Factions\History\StandingEntry;
use Tilttek\Factions\Membership;
use Tilttek\Factions\Rank;
use Tilttek\Factions\Reason;
use Tilttek\Factions\Relation;
use Tilttek\Factions\Roster;
use Tilttek\Factions\Scale;
use Tilttek\Factions\Stance;
use Tilttek\Factions\Standing;
use Tilttek\Factions\Store\InMemoryRosterStore;
use Tilttek\Factions\Subject;
use Tilttek\Factions\Tests\Unit\Fixture\Observed;
use Tilttek\Factions\Tests\Unit\Fixture\StaleStore;
use Tilttek\Factions\Tier;
use Tilttek\Factions\View\FactionView;
use Tilttek\Factions\View\RosterView;
use Tilttek\Factions\View\StandingView;

#[CoversClass(Factions::class)]
#[CoversClass(RosterNotFound::class)]
#[CoversClass(RosterRefused::class)]
#[CoversClass(RosterCreated::class)]
#[CoversClass(FactionAdded::class)]
#[CoversClass(FactionChanged::class)]
#[CoversClass(FactionRemoved::class)]
#[CoversClass(ScaleChanged::class)]
#[CoversClass(StandingChanged::class)]
#[CoversClass(RankChanged::class)]
#[CoversClass(RelationChanged::class)]
#[CoversClass(RosterHandedOver::class)]
#[CoversClass(RosterDeleted::class)]
#[CoversClass(RosterView::class)]
#[CoversClass(FactionView::class)]
#[CoversClass(StandingView::class)]
final class FactionsTest extends TestCase
{
    private const string GM = 'member-1';

    private InMemoryRosterStore $store;

    private MockClock $clock;

    private Observed $observed;

    private Factions $factions;

    #[Override]
    protected function setUp(): void
    {
        $this->store = new InMemoryRosterStore();
        $this->clock = new MockClock('2026-11-25 20:00:00', 'UTC');
        $this->observed = new Observed();
        $this->factions = new Factions($this->store, $this->clock, $this->observed);
    }

    private function roster(): Roster
    {
        return new Roster('campaign-1', self::GM, Scale::standard(), [
            new Faction('guild', 'Guild', 'Runs the docks.', [new Rank('cutpurse', 'Cutpurse'), new Rank('fence', 'Fence')], [new Standing(Subject::party(), 1), new Standing(Subject::character('char-1'), -2)], [new Membership('char-1', 'cutpurse')]),
            new Faction('watch', 'Watch'),
            new Faction('cult', 'Cult', 'The drowned god.', secret: true),
        ], [
            new Relation('watch', 'guild', Stance::Hostile, 'Bribes stopped.'),
            new Relation('cult', 'guild', Stance::Allied, secret: true),
        ]);
    }

    private function created(): Roster
    {
        $kept = $this->factions->create($this->roster());
        $this->observed->take();

        return $kept;
    }

    private function reason(string $text = 'Saved the heir.'): Reason
    {
        return new Reason($text, 'session-3');
    }

    /**
     * @return list<Entry>
     */
    private function history(string $faction): array
    {
        return iterator_to_array($this->factions->history('campaign-1', $faction, self::GM), false);
    }

    public function testARosterIsCreatedWithWhereTheCampaignBegins(): void
    {
        $kept = $this->factions->create($this->roster());

        self::assertSame(1, $kept->version);
        self::assertSame($kept, $this->factions->roster('campaign-1'));
        self::assertEquals([new RosterCreated('campaign-1')], $this->observed->take());
        self::assertSame([], $this->history('guild'));
    }

    public function testANewRosterIsAtVersionZero(): void
    {
        $this->expectException(RosterRefused::class);
        $this->expectExceptionMessageIsOrContains('A new roster is at version 0.');

        $this->factions->create($this->roster()->withVersion(1));
    }

    public function testACampaignHasOneRoster(): void
    {
        $this->created();

        try {
            $this->factions->create(new Roster('campaign-1', 'member-2', Scale::standard()));
            self::fail('Refused');
        } catch (RosterRefused $rosterRefused) {
            self::assertSame('A roster is kept for the campaign campaign-1.', $rosterRefused->getMessage());
            self::assertSame([], $this->observed->take());
        }
    }

    public function testARosterNeverCreatedIsNotFound(): void
    {
        $this->expectException(RosterNotFound::class);
        $this->expectExceptionMessageIsOrContains('No roster of factions is kept for the campaign campaign-9.');

        $this->factions->roster('campaign-9');
    }

    public function testTheCampaignIsAnIdentifier(): void
    {
        $this->expectException(InvalidRoster::class);

        $this->factions->roster('campaign 9');
    }

    public function testOnlyTheGameMasterChangesTheRoster(): void
    {
        $this->created();
        $calls = [
            fn (): mixed => $this->factions->addFaction('campaign-1', new Faction('duke', 'Duke'), 'member-2'),
            fn (): mixed => $this->factions->changeFaction('campaign-1', new Faction('watch', 'The Watch'), 'member-2'),
            fn (): mixed => $this->factions->removeFaction('campaign-1', 'watch', 'member-2'),
            fn (): mixed => $this->factions->changeScale('campaign-1', new Scale(-5, 5, 0, [new Tier('t', 'T', -5)]), 'member-2'),
            fn (): mixed => $this->factions->shift('campaign-1', 'guild', Subject::party(), 1, $this->reason(), 'member-2'),
            fn (): mixed => $this->factions->setStanding('campaign-1', 'guild', Subject::party(), 3, $this->reason(), 'member-2'),
            fn (): mixed => $this->factions->setRank('campaign-1', 'guild', 'char-2', 'fence', $this->reason(), 'member-2'),
            fn (): mixed => $this->factions->relate('campaign-1', new Relation('watch', 'cult', Stance::War), $this->reason(), 'member-2'),
            fn (): mixed => $this->factions->endRelation('campaign-1', 'watch', 'guild', $this->reason(), 'member-2'),
            fn (): mixed => $this->factions->handOver('campaign-1', 'member-2', 'member-2'),
            function (): null {
                $this->factions->delete('campaign-1', 'member-2');

                return null;
            },
            fn (): mixed => $this->factions->history('campaign-1', 'guild', 'member-2'),
            fn (): mixed => $this->factions->view('campaign-1', 'member-2'),
        ];

        foreach ($calls as $call) {
            try {
                $call();
                self::fail('Refused');
            } catch (RosterRefused $refused) {
                self::assertSame('Only the game master of the campaign campaign-1 changes its factions and sees their secrets.', $refused->getMessage());
            }
        }

        self::assertSame(1, $this->factions->roster('campaign-1')->version);
        self::assertSame([], $this->observed->take());
    }

    public function testAFactionIsAddedAfterTheOthers(): void
    {
        $this->created();
        $kept = $this->factions->addFaction('campaign-1', new Faction('duke', 'Duke', ranks: [new Rank('knight', 'Knight')]), self::GM);

        self::assertSame(2, $kept->version);
        self::assertSame(['guild', 'watch', 'cult', 'duke'], array_map(static fn (Faction $faction): string => $faction->key, $kept->factions));
        self::assertEquals([new FactionAdded('campaign-1', 'duke')], $this->observed->take());
    }

    public function testAFactionJoinsWithoutStandingsOrMembers(): void
    {
        $this->created();

        foreach ([
            new Faction('duke', 'Duke', standings: [new Standing(Subject::party(), 1)]),
            new Faction('duke', 'Duke', ranks: [new Rank('knight', 'Knight')], members: [new Membership('char-1', 'knight')]),
        ] as $faction) {
            try {
                $this->factions->addFaction('campaign-1', $faction, self::GM);
                self::fail('Refused');
            } catch (RosterRefused $refused) {
                self::assertSame('A faction joins without standings or members: they are set with a reason.', $refused->getMessage());
            }
        }

        self::assertSame([], $this->observed->take());
    }

    public function testAnAddedFactionHasANewKey(): void
    {
        $this->created();

        $this->expectException(InvalidRoster::class);
        $this->expectExceptionMessageIsOrContains('A faction of the roster has the key watch.');

        $this->factions->addFaction('campaign-1', new Faction('watch', 'Watch'), self::GM);
    }

    public function testAFactionChangesItsDetailsAndKeepsItsStandingsAndMembers(): void
    {
        $before = $this->created();
        $kept = $this->factions->changeFaction('campaign-1', new Faction('guild', 'The Guild', 'Gone legit.', [new Rank('cutpurse', 'Apprentice')], [new Standing(Subject::party(), -3)], secret: true), self::GM);
        $guild = $kept->get('guild');

        self::assertSame('The Guild', $guild->name);
        self::assertSame('Gone legit.', $guild->summary);
        self::assertTrue($guild->secret);
        self::assertSame($before->get('guild')->standings, $guild->standings);
        self::assertSame($before->get('guild')->members, $guild->members);
        self::assertSame(['guild', 'watch', 'cult'], array_map(static fn (Faction $faction): string => $faction->key, $kept->factions));
        self::assertEquals([new FactionChanged('campaign-1', 'guild')], $this->observed->take());
    }

    public function testAFactionChangedToWhatItWasKeepsAndTellsNothing(): void
    {
        $before = $this->created();
        $same = $this->factions->changeFaction('campaign-1', new Faction('guild', 'Guild', 'Runs the docks.', [new Rank('cutpurse', 'Cutpurse'), new Rank('fence', 'Fence')]), self::GM);

        self::assertSame($before, $same);
        self::assertSame([], $this->observed->take());
    }

    public function testOnlyAFactionOfTheRosterChanges(): void
    {
        $this->created();

        $this->expectException(InvalidRoster::class);
        $this->expectExceptionMessageIsOrContains('No faction of the roster has the key duke.');

        $this->factions->changeFaction('campaign-1', new Faction('duke', 'Duke'), self::GM);
    }

    public function testAFactionIsRemovedWithItsRelationsAndItsHistoryStays(): void
    {
        $this->created();
        $this->factions->shift('campaign-1', 'guild', Subject::party(), 1, $this->reason(), self::GM);
        $this->observed->take();
        $kept = $this->factions->removeFaction('campaign-1', 'guild', self::GM);

        self::assertSame(['watch', 'cult'], array_map(static fn (Faction $faction): string => $faction->key, $kept->factions));
        self::assertSame([], $kept->relations);
        self::assertEquals([new FactionRemoved('campaign-1', 'guild')], $this->observed->take());
        self::assertCount(1, $this->history('guild'));
    }

    public function testTheScaleChangesWhenEveryStandingFits(): void
    {
        $this->created();
        $wider = new Scale(-5, 5, 0, [new Tier('foe', 'Foe', -5), new Tier('friend', 'Friend', 1)]);
        $kept = $this->factions->changeScale('campaign-1', $wider, self::GM);

        self::assertSame($wider, $kept->scale);
        self::assertEquals([new ScaleChanged('campaign-1')], $this->observed->take());
        self::assertSame($kept, $this->factions->changeScale('campaign-1', new Scale(-5, 5, 0, [new Tier('foe', 'Foe', -5), new Tier('friend', 'Friend', 1)]), self::GM));
        self::assertSame([], $this->observed->take());
    }

    public function testTheScaleDoesNotChangeOverAStandingOffIt(): void
    {
        $this->created();

        $this->expectException(InvalidRoster::class);
        $this->expectExceptionMessageIsOrContains('The standing -2 of character:char-1 with the faction guild is off the scale, from -1 to 1.');

        $this->factions->changeScale('campaign-1', new Scale(-1, 1, 0, [new Tier('t', 'T', -1)]), self::GM);
    }

    public function testAShiftMovesAStandingAndKeepsItsReason(): void
    {
        $this->created();
        $kept = $this->factions->shift('campaign-1', 'guild', Subject::party(), 1, $this->reason(), self::GM);

        self::assertSame(2, $kept->standingOf('guild', Subject::party()));
        self::assertSame(2, $kept->version);
        self::assertEquals([new StandingChanged('campaign-1', 'guild', 'party')], $this->observed->take());

        $entries = $this->history('guild');
        self::assertCount(1, $entries);
        $entry = $entries[0];
        self::assertInstanceOf(StandingEntry::class, $entry);
        self::assertSame('campaign-1', $entry->campaign);
        self::assertSame('guild', $entry->faction);
        self::assertSame('party', $entry->subject->key);
        self::assertSame(1, $entry->before);
        self::assertSame(2, $entry->after);
        self::assertSame('Saved the heir.', $entry->reason->text);
        self::assertSame('session-3', $entry->reason->session);
        self::assertSame(self::GM, $entry->by);
        self::assertSame('2026-11-25T20:00:00+00:00', $entry->at->format(DateTimeImmutable::ATOM));
    }

    public function testAShiftDownFromTheStartSetsAStandingNeverSet(): void
    {
        $this->created();
        $kept = $this->factions->shift('campaign-1', 'watch', Subject::character('char-2'), -2, $this->reason('Caught.'), self::GM);

        self::assertSame(-2, $kept->standingOf('watch', Subject::character('char-2')));
        $entry = $this->history('watch')[0];
        self::assertInstanceOf(StandingEntry::class, $entry);
        self::assertSame(0, $entry->before);
        self::assertSame(-2, $entry->after);
        self::assertEquals([new StandingChanged('campaign-1', 'watch', 'character:char-2')], $this->observed->take());
    }

    public function testAShiftStopsAtTheEndsOfTheScale(): void
    {
        $this->created();

        self::assertSame(3, $this->factions->shift('campaign-1', 'guild', Subject::party(), 9, $this->reason(), self::GM)->standingOf('guild', Subject::party()));
        self::assertSame(-3, $this->factions->shift('campaign-1', 'guild', Subject::party(), -200, $this->reason(), self::GM)->standingOf('guild', Subject::party()));
        $this->observed->take();

        $same = $this->factions->shift('campaign-1', 'guild', Subject::party(), -1, $this->reason(), self::GM);

        self::assertSame(3, $same->version);
        self::assertSame([], $this->observed->take());
        self::assertCount(2, $this->history('guild'));
    }

    public function testAShiftMovesBetweenOneAndTwoHundredSteps(): void
    {
        $this->created();
        self::assertSame(-3, $this->factions->shift('campaign-1', 'guild', Subject::party(), Factions::MAX_STEPS * -1, $this->reason(), self::GM)->standingOf('guild', Subject::party()));

        foreach ([0, 201, -201] as $steps) {
            try {
                $this->factions->shift('campaign-1', 'guild', Subject::party(), $steps, $this->reason(), self::GM);
                self::fail('Refused: '.$steps);
            } catch (InvalidRoster $refused) {
                self::assertSame('A shift moves a standing by 1 to 200 steps, up or down.', $refused->getMessage());
            }
        }
    }

    public function testAShiftNamesAFactionOfTheRoster(): void
    {
        $this->created();

        $this->expectException(InvalidRoster::class);
        $this->expectExceptionMessageIsOrContains('No faction of the roster has the key duke.');

        $this->factions->shift('campaign-1', 'duke', Subject::party(), 1, $this->reason(), self::GM);
    }

    public function testAStandingIsSetWithinTheScale(): void
    {
        $this->created();
        $kept = $this->factions->setStanding('campaign-1', 'guild', Subject::character('char-1'), 3, $this->reason(), self::GM);

        self::assertSame(3, $kept->standingOf('guild', Subject::character('char-1')));
        $entry = $this->history('guild')[0];
        self::assertInstanceOf(StandingEntry::class, $entry);
        self::assertSame(-2, $entry->before);
        self::assertSame(3, $entry->after);
        self::assertEquals([new StandingChanged('campaign-1', 'guild', 'character:char-1')], $this->observed->take());

        self::assertSame(-3, $this->factions->setStanding('campaign-1', 'guild', Subject::party(), -3, $this->reason(), self::GM)->standingOf('guild', Subject::party()));
    }

    public function testAStandingSetWhereItIsKeepsAndTellsNothing(): void
    {
        $before = $this->created();

        self::assertSame($before, $this->factions->setStanding('campaign-1', 'guild', Subject::party(), 1, $this->reason(), self::GM));
        self::assertSame($before, $this->factions->setStanding('campaign-1', 'watch', Subject::party(), 0, $this->reason(), self::GM));
        self::assertSame([], $this->observed->take());
        self::assertSame([], $this->history('guild'));
        self::assertSame([], $this->factions->roster('campaign-1')->get('watch')->standings);
    }

    public function testAStandingOffTheScaleIsRefused(): void
    {
        $this->created();

        foreach ([4, -4] as $standing) {
            try {
                $this->factions->setStanding('campaign-1', 'guild', Subject::party(), $standing, $this->reason(), self::GM);
                self::fail('Refused');
            } catch (InvalidRoster $refused) {
                self::assertSame(\sprintf('The standing %d is off the scale, from -3 to 3.', $standing), $refused->getMessage());
            }
        }
    }

    public function testACharacterJoinsRisesAndLeavesAFactionWithReasons(): void
    {
        $this->created();
        $joined = $this->factions->setRank('campaign-1', 'guild', 'char-2', 'cutpurse', $this->reason('Joined.'), self::GM);
        $risen = $this->factions->setRank('campaign-1', 'guild', 'char-2', 'fence', $this->reason('Rose.'), self::GM);
        $left = $this->factions->setRank('campaign-1', 'guild', 'char-1', null, $this->reason('Left.'), self::GM);

        self::assertSame('cutpurse', $joined->get('guild')->membership('char-2')?->rank);
        self::assertSame('fence', $risen->get('guild')->membership('char-2')?->rank);
        self::assertNull($left->get('guild')->membership('char-1'));
        self::assertEquals([
            new RankChanged('campaign-1', 'guild', 'char-2'),
            new RankChanged('campaign-1', 'guild', 'char-2'),
            new RankChanged('campaign-1', 'guild', 'char-1'),
        ], $this->observed->take());

        $entries = $this->history('guild');
        self::assertSame(['Left.', 'Rose.', 'Joined.'], array_map(static fn (Entry $entry): string => $entry->reason->text, $entries));
        self::assertInstanceOf(RankEntry::class, $entries[0]);
        self::assertSame(['char-1', 'cutpurse', null], [$entries[0]->character, $entries[0]->before, $entries[0]->after]);
        self::assertInstanceOf(RankEntry::class, $entries[1]);
        self::assertSame(['char-2', 'cutpurse', 'fence'], [$entries[1]->character, $entries[1]->before, $entries[1]->after]);
        self::assertInstanceOf(RankEntry::class, $entries[2]);
        self::assertSame([null, 'cutpurse', 'campaign-1', 'guild', self::GM], [$entries[2]->before, $entries[2]->after, $entries[2]->campaign, $entries[2]->faction, $entries[2]->by]);
    }

    public function testARankAlreadyHeldKeepsAndTellsNothing(): void
    {
        $before = $this->created();

        self::assertSame($before, $this->factions->setRank('campaign-1', 'guild', 'char-1', 'cutpurse', $this->reason(), self::GM));
        self::assertSame($before, $this->factions->setRank('campaign-1', 'guild', 'char-9', null, $this->reason(), self::GM));
        self::assertSame([], $this->observed->take());
        self::assertSame([], $this->history('guild'));
    }

    public function testARankIsOneOfTheFaction(): void
    {
        $this->created();

        $this->expectException(InvalidRoster::class);
        $this->expectExceptionMessageIsOrContains('The character char-2 holds the rank duke, which the faction guild lacks.');

        $this->factions->setRank('campaign-1', 'guild', 'char-2', 'duke', $this->reason(), self::GM);
    }

    public function testTheCharacterOfARankIsAnIdentifier(): void
    {
        $this->created();

        $this->expectException(InvalidRoster::class);
        $this->expectExceptionMessageIsOrContains('The identifier of a character is');

        $this->factions->setRank('campaign-1', 'guild', 'char 9', null, $this->reason(), self::GM);
    }

    public function testARelationIsSetChangedAndEndedWithReasons(): void
    {
        $this->created();
        $set = $this->factions->relate('campaign-1', new Relation('watch', 'cult', Stance::Rival), $this->reason('Raided.'), self::GM);
        $changed = $this->factions->relate('campaign-1', new Relation('cult', 'watch', Stance::War, secret: true), $this->reason('Burned.'), self::GM);
        $ended = $this->factions->endRelation('campaign-1', 'watch', 'cult', $this->reason('Truce.'), self::GM);

        self::assertSame(Stance::Rival, $set->relation('cult', 'watch')?->stance);
        self::assertSame(Stance::War, $changed->relation('cult', 'watch')?->stance);
        self::assertNull($ended->relation('cult', 'watch'));
        self::assertCount(2, $ended->relations);
        self::assertEquals([
            new RelationChanged('campaign-1', 'cult', 'watch'),
            new RelationChanged('campaign-1', 'cult', 'watch'),
            new RelationChanged('campaign-1', 'cult', 'watch'),
        ], $this->observed->take());

        $entries = $this->history('watch');
        self::assertSame(['Truce.', 'Burned.', 'Raided.'], array_map(static fn (Entry $entry): string => $entry->reason->text, $entries));
        self::assertInstanceOf(RelationEntry::class, $entries[0]);
        self::assertSame(['cult', 'watch', Stance::War, null], [$entries[0]->faction, $entries[0]->other, $entries[0]->before?->stance, $entries[0]->after]);
        self::assertSame([self::GM, 'campaign-1'], [$entries[0]->by, $entries[0]->campaign]);
        self::assertInstanceOf(RelationEntry::class, $entries[1]);
        self::assertSame([Stance::Rival, Stance::War], [$entries[1]->before?->stance, $entries[1]->after?->stance]);
        self::assertInstanceOf(RelationEntry::class, $entries[2]);
        self::assertSame(['cult', 'watch', null, Stance::Rival], [$entries[2]->faction, $entries[2]->other, $entries[2]->before, $entries[2]->after?->stance]);
        self::assertCount(3, $this->history('cult'));
    }

    public function testARelationAlreadySoKeepsAndTellsNothing(): void
    {
        $before = $this->created();

        self::assertSame($before, $this->factions->relate('campaign-1', new Relation('guild', 'watch', Stance::Hostile, 'Bribes stopped.'), $this->reason(), self::GM));
        self::assertSame([], $this->observed->take());
        self::assertNotSame($before, $this->factions->relate('campaign-1', new Relation('guild', 'watch', Stance::Hostile), $this->reason(), self::GM));
    }

    public function testARelationNamesFactionsOfTheRoster(): void
    {
        $this->created();

        $this->expectException(InvalidRoster::class);
        $this->expectExceptionMessageIsOrContains('The relation between duke and watch names a faction the roster lacks.');

        $this->factions->relate('campaign-1', new Relation('watch', 'duke', Stance::Allied), $this->reason(), self::GM);
    }

    public function testOnlyARelationHeldEnds(): void
    {
        $this->created();

        try {
            $this->factions->endRelation('campaign-1', 'watch', 'cult', $this->reason(), self::GM);
            self::fail('Refused');
        } catch (InvalidRoster $invalidRoster) {
            self::assertSame('The factions watch and cult have no relation.', $invalidRoster->getMessage());
            self::assertSame([], $this->observed->take());
        }
    }

    public function testTheRosterIsHandedOver(): void
    {
        $before = $this->created();

        self::assertSame($before, $this->factions->handOver('campaign-1', self::GM, self::GM));
        self::assertSame([], $this->observed->take());

        $kept = $this->factions->handOver('campaign-1', 'member-2', self::GM);

        self::assertSame('member-2', $kept->gameMaster);
        self::assertEquals([new RosterHandedOver('campaign-1', 'member-2')], $this->observed->take());

        $this->expectException(RosterRefused::class);
        $this->factions->view('campaign-1', self::GM);
    }

    public function testTheRosterIsDeletedWithItsHistory(): void
    {
        $this->created();
        $this->factions->shift('campaign-1', 'guild', Subject::party(), 1, $this->reason(), self::GM);
        $this->observed->take();
        $this->factions->delete('campaign-1', self::GM);

        self::assertNull($this->store->roster('campaign-1'));
        self::assertSame([], [...$this->store->history('campaign-1', 'guild', 10)]);
        self::assertEquals([new RosterDeleted('campaign-1')], $this->observed->take());
    }

    public function testTheHistoryIsReadNewestFirstWithinItsBounds(): void
    {
        $this->created();

        foreach (range(1, 3) as $time) {
            $this->clock->sleep(60);
            $this->factions->shift('campaign-1', 'watch', Subject::party(), -1, $this->reason('Step '.$time), self::GM);
        }

        self::assertSame(['Step 3', 'Step 2'], array_map(static fn (Entry $entry): string => $entry->reason->text, [...$this->factions->history('campaign-1', 'watch', self::GM, 2)]));
        self::assertSame(['Step 3'], array_map(static fn (Entry $entry): string => $entry->reason->text, [...$this->factions->history('campaign-1', 'watch', self::GM, 1)]));
        self::assertCount(3, [...$this->factions->history('campaign-1', 'watch', self::GM, Factions::MAX_READ)]);
        self::assertCount(3, $this->history('watch'));
        self::assertSame('2026-11-25T20:03:00+00:00', $this->history('watch')[0]->at->format(DateTimeImmutable::ATOM));

        foreach ([0, 501] as $limit) {
            try {
                $this->factions->history('campaign-1', 'watch', self::GM, $limit);
                self::fail('Refused');
            } catch (InvalidRoster $refused) {
                self::assertSame('A read of the history is of 1 to 500 entries.', $refused->getMessage());
            }
        }

        $this->expectException(InvalidRoster::class);
        $this->expectExceptionMessageIsOrContains('The key of a faction is');
        $this->factions->history('campaign-1', 'the watch', self::GM);
    }

    public function testTheHistoryAsksTheStoreForAHundredEntriesByDefault(): void
    {
        $this->created();

        foreach (range(1, 101) as $time) {
            $this->factions->setStanding('campaign-1', 'watch', Subject::party(), 0 === $time % 2 ? 1 : -1, $this->reason(), self::GM);
        }

        self::assertCount(100, $this->history('watch'));
    }

    public function testTheGameMasterSeesEverything(): void
    {
        $this->created();
        $view = $this->factions->view('campaign-1', self::GM);

        self::assertSame('campaign-1', $view->campaign);
        self::assertSame(1, $view->version);
        self::assertEquals(Scale::standard(), $view->scale);
        self::assertSame(['guild', 'watch', 'cult'], array_map(static fn (FactionView $faction): string => $faction->key, $view->factions));
        self::assertCount(2, $view->relations);
        self::assertSame([
            'key' => 'guild',
            'name' => 'Guild',
            'summary' => 'Runs the docks.',
            'secret' => false,
            'ranks' => [['key' => 'cutpurse', 'title' => 'Cutpurse'], ['key' => 'fence', 'title' => 'Fence']],
            'standings' => [
                ['subject' => 'party', 'value' => 1, 'tier' => 'friendly', 'label' => 'Friendly'],
                ['subject' => 'character:char-1', 'value' => -2, 'tier' => 'unfriendly', 'label' => 'Unfriendly'],
            ],
            'members' => [['character' => 'char-1', 'rank' => 'cutpurse']],
        ], $view->factions[0]->toArray());
        self::assertSame([['subject' => 'party', 'value' => 0, 'tier' => 'neutral', 'label' => 'Neutral']], $view->factions[1]->toArray()['standings']);
        self::assertTrue($view->factions[2]->secret);
        self::assertSame('The drowned god.', $view->factions[2]->summary);
    }

    public function testThePartyComesFirstInTheGameMastersViewWhereverItWasSet(): void
    {
        $this->factions->create(new Roster('campaign-1', self::GM, Scale::standard(), [
            new Faction('guild', 'Guild', standings: [new Standing(Subject::character('char-1'), 2), new Standing(Subject::party(), -1)]),
        ]));

        self::assertSame(
            [['subject' => 'party', 'value' => -1, 'tier' => 'unfriendly', 'label' => 'Unfriendly'], ['subject' => 'character:char-1', 'value' => 2, 'tier' => 'friendly', 'label' => 'Friendly']],
            $this->factions->view('campaign-1', self::GM)->factions[0]->toArray()['standings'],
        );
    }

    public function testTheViewGivesAnArrayForAPage(): void
    {
        $this->created();

        self::assertSame([
            'campaign' => 'campaign-1',
            'version' => 1,
            'scale' => [
                'min' => -3,
                'max' => 3,
                'start' => 0,
                'tiers' => [
                    ['key' => 'hostile', 'label' => 'Hostile', 'from' => -3],
                    ['key' => 'unfriendly', 'label' => 'Unfriendly', 'from' => -2],
                    ['key' => 'neutral', 'label' => 'Neutral', 'from' => 0],
                    ['key' => 'friendly', 'label' => 'Friendly', 'from' => 1],
                    ['key' => 'allied', 'label' => 'Allied', 'from' => 3],
                ],
            ],
            'factions' => [],
            'relations' => [['first' => 'guild', 'second' => 'watch', 'stance' => 'hostile', 'note' => 'Bribes stopped.', 'secret' => false]],
        ], [...$this->factions->playerView('campaign-1')->toArray(), 'factions' => []]);
    }

    public function testAPlayerViewIsAnArrayOfArraysToTheDepth(): void
    {
        $this->created();
        $view = $this->factions->playerView('campaign-1', 'char-1');
        $array = $view->toArray();

        self::assertSame([$view->factions[0]->toArray(), $view->factions[1]->toArray()], $array['factions']);
        self::assertIsArray($array['factions'][0]);
    }

    public function testAPlayerSeesNoSecretAndOnlyTheirOwnStandingAndRank(): void
    {
        $this->created();
        $view = $this->factions->playerView('campaign-1', 'char-1');

        self::assertSame(['guild', 'watch'], array_map(static fn (FactionView $faction): string => $faction->key, $view->factions));
        self::assertSame(['guild watch'], array_map(static fn (Relation $relation): string => $relation->key, $view->relations));
        self::assertSame([
            'key' => 'guild',
            'name' => 'Guild',
            'summary' => 'Runs the docks.',
            'secret' => false,
            'ranks' => [['key' => 'cutpurse', 'title' => 'Cutpurse'], ['key' => 'fence', 'title' => 'Fence']],
            'standings' => [
                ['subject' => 'party', 'value' => 1, 'tier' => 'friendly', 'label' => 'Friendly'],
                ['subject' => 'character:char-1', 'value' => -2, 'tier' => 'unfriendly', 'label' => 'Unfriendly'],
            ],
            'members' => [['character' => 'char-1', 'rank' => 'cutpurse']],
        ], $view->factions[0]->toArray());
        self::assertSame([
            ['subject' => 'party', 'value' => 0, 'tier' => 'neutral', 'label' => 'Neutral'],
            ['subject' => 'character:char-1', 'value' => 0, 'tier' => 'neutral', 'label' => 'Neutral'],
        ], $view->factions[1]->toArray()['standings']);
        self::assertSame([], $view->factions[1]->members);
    }

    public function testAnotherPlayerSeesNeitherTheStandingNorTheRankOfAnother(): void
    {
        $this->created();
        $guild = $this->factions->playerView('campaign-1', 'char-2')->factions[0];

        self::assertSame(['party', 'character:char-2'], array_map(static fn (StandingView $standing): string => $standing->subject, $guild->standings));
        self::assertSame([], $guild->members);

        $table = $this->factions->playerView('campaign-1')->factions[0];
        self::assertSame(['party'], array_map(static fn (StandingView $standing): string => $standing->subject, $table->standings));
        self::assertSame([], $table->members);
    }

    public function testASecretRelationOrARelationWithASecretFactionStaysHidden(): void
    {
        $this->factions->create(new Roster('campaign-1', self::GM, Scale::standard(), [
            new Faction('guild', 'Guild'),
            new Faction('cult', 'Cult', secret: true),
            new Faction('watch', 'Watch'),
            new Faction('zealots', 'Zealots', secret: true),
            new Faction('duke', 'Duke'),
        ], [
            new Relation('guild', 'cult', Stance::Allied),
            new Relation('cult', 'watch', Stance::Allied),
            new Relation('guild', 'zealots', Stance::War),
            new Relation('guild', 'watch', Stance::Rival),
            new Relation('duke', 'watch', Stance::Friendly, secret: true),
        ]));

        self::assertSame(['guild watch'], array_map(static fn (Relation $relation): string => $relation->key, $this->factions->playerView('campaign-1')->relations));
    }

    public function testThePlayerViewChecksTheCharacterEvenWithoutAFaction(): void
    {
        $this->factions->create(new Roster('campaign-1', self::GM, Scale::standard()));

        $this->expectException(InvalidRoster::class);

        $this->factions->playerView('campaign-1', 'char 1');
    }

    public function testTheServiceWorksWithoutAnObserver(): void
    {
        $factions = new Factions(new InMemoryRosterStore(), $this->clock);
        $factions->create($this->roster());

        self::assertSame(2, $factions->shift('campaign-1', 'guild', Subject::party(), 1, $this->reason(), self::GM)->version);
    }

    public function testAChangeOnAStaleRosterIsNotKept(): void
    {
        $factions = new Factions(new StaleStore($this->roster()->withVersion(3)), $this->clock, $this->observed);

        try {
            $factions->shift('campaign-1', 'guild', Subject::party(), 1, $this->reason(), self::GM);
            self::fail('Refused');
        } catch (RosterConflict) {
            self::assertSame([], $this->observed->take());
        }
    }
}
