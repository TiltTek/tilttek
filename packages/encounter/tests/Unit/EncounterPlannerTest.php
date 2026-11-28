<?php

declare(strict_types=1);

namespace Tilttek\Encounter\Tests\Unit;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\Encounter\EncounterPlanner;
use Tilttek\Encounter\Event\EncounterForgotten;
use Tilttek\Encounter\Event\EncounterLaunched;
use Tilttek\Encounter\Event\EncounterPrepared;
use Tilttek\Encounter\Event\EncounterRetitled;
use Tilttek\Encounter\Event\ParticipantAdded;
use Tilttek\Encounter\Event\ParticipantChanged;
use Tilttek\Encounter\Event\ParticipantMoved;
use Tilttek\Encounter\Event\ParticipantRemoved;
use Tilttek\Encounter\Exception\EncounterConflict;
use Tilttek\Encounter\Exception\EncounterNotFound;
use Tilttek\Encounter\Exception\EncounterRefused;
use Tilttek\Encounter\Exception\InvalidEncounter;
use Tilttek\Encounter\Exception\UnknownSystem;
use Tilttek\Encounter\Initiative\RosterEntry;
use Tilttek\Encounter\Kind;
use Tilttek\Encounter\Participant;
use Tilttek\Encounter\Rulebooks;
use Tilttek\Encounter\Store\InMemoryEncounterStore;
use Tilttek\Encounter\Tests\Unit\Fixture\Observed;
use Tilttek\Encounter\Tests\Unit\Fixture\Systems;
use Tilttek\Encounter\Tests\Unit\Fixture\Tracker;

#[CoversClass(EncounterPlanner::class)]
#[CoversClass(InMemoryEncounterStore::class)]
#[CoversClass(EncounterConflict::class)]
#[CoversClass(EncounterNotFound::class)]
#[CoversClass(EncounterRefused::class)]
#[CoversClass(EncounterPrepared::class)]
#[CoversClass(EncounterRetitled::class)]
#[CoversClass(ParticipantAdded::class)]
#[CoversClass(ParticipantChanged::class)]
#[CoversClass(ParticipantMoved::class)]
#[CoversClass(ParticipantRemoved::class)]
#[CoversClass(EncounterLaunched::class)]
#[CoversClass(EncounterForgotten::class)]
final class EncounterPlannerTest extends TestCase
{
    private InMemoryEncounterStore $store;

    private Observed $observed;

    private Tracker $tracker;

    private EncounterPlanner $planner;

    #[Override]
    protected function setUp(): void
    {
        $this->store = new InMemoryEncounterStore();
        $this->observed = new Observed();
        $this->tracker = new Tracker();
        $this->planner = new EncounterPlanner($this->store, new Rulebooks(Systems::budget(), Systems::level()), $this->observed, $this->tracker);
    }

    private function hero(string $id = 'hero', string $level = '2'): Participant
    {
        return new Participant($id, Kind::Character, $level, 'player-'.$id);
    }

    private function foe(string $id, int $quantity = 1, string $rating = '1'): Participant
    {
        return new Participant($id, Kind::Adversary, $rating, 'gm', $quantity);
    }

    /**
     * @return list<string>
     */
    private function order(string $id = 'ambush'): array
    {
        return array_map(static fn (Participant $participant): string => $participant->id, $this->planner->encounter($id)->participants);
    }

    private function prepared(): void
    {
        $this->planner->prepare('ambush', 'gm', 'budget-game', 'The ambush', [$this->hero(), $this->foe('a'), $this->foe('b'), $this->foe('c')]);
        $this->observed->take();
    }

    public function testAnEncounterIsPreparedAndKept(): void
    {
        $encounter = $this->planner->prepare('ambush', 'gm', 'budget-game', 'The ambush', [$this->hero()]);

        self::assertSame(1, $encounter->revision);
        self::assertSame('The ambush', $encounter->title);
        self::assertEquals([new EncounterPrepared('ambush', 'gm')], $this->observed->take());
        self::assertSame($encounter, $this->planner->encounter('ambush'));
        self::assertSame([], $this->planner->prepare('empty', 'gm', 'level-game')->participants);
        self::assertNull($this->planner->encounter('empty')->title);
    }

    public function testAnEncounterIsPreparedForASystemWithRules(): void
    {
        try {
            $this->planner->prepare('ambush', 'gm', 'other-game');
            self::fail('An encounter is prepared for a system without rules.');
        } catch (UnknownSystem $unknownSystem) {
            self::assertSame('No rules of difficulty are declared for the game system other-game.', $unknownSystem->getMessage());
        }

        self::assertNull($this->store->encounter('ambush'));
        self::assertSame([], $this->observed->take());
        self::assertSame(1, new EncounterPlanner($this->store)->prepare('ambush', 'gm', 'other-game')->revision);
    }

    public function testAnEncounterIsPreparedOnce(): void
    {
        $this->planner->prepare('ambush', 'gm', 'budget-game');
        $this->observed->take();

        $this->expectException(EncounterConflict::class);
        $this->expectExceptionMessageIsOrContains('The encounter ambush already exists.');

        try {
            $this->planner->prepare('ambush', 'gm-2', 'budget-game');
        } finally {
            self::assertSame([], $this->observed->take());
            self::assertSame('gm', $this->planner->encounter('ambush')->gameMaster);
        }
    }

    public function testAMissingEncounterIsNotFound(): void
    {
        $this->expectException(EncounterNotFound::class);
        $this->expectExceptionMessageIsOrContains('There is no encounter ambush.');

        $this->planner->encounter('ambush');
    }

    public function testAnIdentifierIsCheckedBeforeTheStoreIsRead(): void
    {
        $this->expectException(InvalidEncounter::class);
        $this->expectExceptionMessageIsOrContains('The identifier of an encounter');

        $this->planner->encounter('a b');
    }

    public function testTheTitleChanges(): void
    {
        $this->prepared();

        self::assertSame('Night ambush', $this->planner->retitle('ambush', 'gm', 'Night ambush')->title);
        self::assertEquals([new EncounterRetitled('ambush')], $this->observed->take());
        self::assertSame(2, $this->planner->retitle('ambush', 'gm', ' Night ambush ')->revision);
        self::assertSame([], $this->observed->take());
        self::assertNull($this->planner->retitle('ambush', 'gm', null)->title);
        self::assertSame(3, $this->planner->encounter('ambush')->revision);
    }

    public function testTheSameTitleChangesNothing(): void
    {
        $this->prepared();

        $encounter = $this->planner->retitle('ambush', 'gm', 'The ambush');

        self::assertSame(1, $encounter->revision);
        self::assertSame([], $this->observed->take());
    }

    public function testAParticipantIsAddedLastOrBesideAnother(): void
    {
        $this->prepared();

        $this->planner->add('ambush', 'gm', $this->foe('d'));
        self::assertSame(['hero', 'a', 'b', 'c', 'd'], $this->order());
        self::assertEquals([new ParticipantAdded('ambush', 'd')], $this->observed->take());

        $this->planner->add('ambush', 'gm', $this->foe('e'), before: 'hero');
        self::assertSame(['e', 'hero', 'a', 'b', 'c', 'd'], $this->order());

        $this->planner->add('ambush', 'gm', $this->foe('f'), after: 'd');
        self::assertSame(['e', 'hero', 'a', 'b', 'c', 'd', 'f'], $this->order());

        $this->planner->add('ambush', 'gm', $this->foe('g'), before: 'b');
        self::assertSame(['e', 'hero', 'a', 'g', 'b', 'c', 'd', 'f'], $this->order());

        $added = $this->planner->add('ambush', 'gm', $this->foe('h'), after: 'a');
        self::assertSame(['e', 'hero', 'a', 'h', 'g', 'b', 'c', 'd', 'f'], $this->order());
        self::assertSame(6, $added->revision);
    }

    public function testAParticipantIsAddedBesideOneTheEncounterHolds(): void
    {
        $this->prepared();

        $this->expectException(EncounterRefused::class);
        $this->expectExceptionMessageIsOrContains('The encounter ambush has no participant z.');

        $this->planner->add('ambush', 'gm', $this->foe('d'), after: 'z');
    }

    public function testAParticipantIsAddedOnOneSideOnly(): void
    {
        $this->prepared();

        $this->expectException(InvalidEncounter::class);
        $this->expectExceptionMessageIsOrContains('A participant comes before another one or after it, not both.');

        $this->planner->add('ambush', 'gm', $this->foe('d'), before: 'a', after: 'b');
    }

    public function testAParticipantIsAddedOnce(): void
    {
        $this->prepared();

        $this->expectException(InvalidEncounter::class);
        $this->expectExceptionMessageIsOrContains('The participant a comes twice in the encounter.');

        try {
            $this->planner->add('ambush', 'gm', $this->foe('a'));
        } finally {
            self::assertSame([], $this->observed->take());
        }
    }

    public function testAParticipantChangesInItsPlace(): void
    {
        $this->prepared();
        $changed = $this->foe('b', 4, '1/2');

        $encounter = $this->planner->change('ambush', 'gm', $changed);

        self::assertSame(['hero', 'a', 'b', 'c'], $this->order());
        self::assertSame($changed, $encounter->participant('b'));
        self::assertSame(2, $encounter->revision);
        self::assertEquals([new ParticipantChanged('ambush', 'b')], $this->observed->take());
    }

    public function testTheSameParticipantChangesNothing(): void
    {
        $this->prepared();

        self::assertSame(1, $this->planner->change('ambush', 'gm', $this->foe('b'))->revision);
        self::assertSame([], $this->observed->take());
    }

    public function testAParticipantTheEncounterDoesNotHoldIsNotChanged(): void
    {
        $this->prepared();

        $this->expectException(EncounterRefused::class);
        $this->expectExceptionMessageIsOrContains('The encounter ambush has no participant z.');

        $this->planner->change('ambush', 'gm', $this->foe('z'));
    }

    public function testAParticipantIsRemoved(): void
    {
        $this->prepared();

        $encounter = $this->planner->remove('ambush', 'gm', 'b');

        self::assertSame(['hero', 'a', 'c'], $this->order());
        self::assertSame(2, $encounter->revision);
        self::assertEquals([new ParticipantRemoved('ambush', 'b')], $this->observed->take());
    }

    public function testAParticipantTheEncounterDoesNotHoldIsNotRemoved(): void
    {
        $this->prepared();

        $this->expectException(EncounterRefused::class);
        $this->expectExceptionMessageIsOrContains('The encounter ambush has no participant z.');

        $this->planner->remove('ambush', 'gm', 'z');
    }

    public function testAParticipantMovesBeforeOrAfterAnother(): void
    {
        $this->prepared();

        $this->planner->move('ambush', 'gm', 'c', before: 'hero');
        self::assertSame(['c', 'hero', 'a', 'b'], $this->order());
        self::assertEquals([new ParticipantMoved('ambush', 'c')], $this->observed->take());

        $this->planner->move('ambush', 'gm', 'c', after: 'b');
        self::assertSame(['hero', 'a', 'b', 'c'], $this->order());

        $this->planner->move('ambush', 'gm', 'hero', after: 'a');
        self::assertSame(['a', 'hero', 'b', 'c'], $this->order());

        $moved = $this->planner->move('ambush', 'gm', 'c', before: 'b');
        self::assertSame(['a', 'hero', 'c', 'b'], $this->order());
        self::assertSame(5, $moved->revision);
    }

    public function testAMoveThatKeepsTheOrderChangesNothing(): void
    {
        $this->prepared();

        self::assertSame(1, $this->planner->move('ambush', 'gm', 'a', after: 'hero')->revision);
        self::assertSame(1, $this->planner->move('ambush', 'gm', 'a', before: 'b')->revision);
        self::assertSame([], $this->observed->take());
    }

    public function testAMoveNamesWhereTo(): void
    {
        $this->prepared();

        $this->expectException(InvalidEncounter::class);
        $this->expectExceptionMessageIsOrContains('A participant moves before or after another one.');

        $this->planner->move('ambush', 'gm', 'a');
    }

    public function testAMoveNamesAnotherParticipant(): void
    {
        $this->prepared();

        $this->expectException(EncounterRefused::class);
        $this->expectExceptionMessageIsOrContains('The participant a moves before or after another one.');

        $this->planner->move('ambush', 'gm', 'a', after: 'a');
    }

    public function testAMoveBeforeItselfIsRefused(): void
    {
        $this->prepared();

        $this->expectException(EncounterRefused::class);
        $this->expectExceptionMessageIsOrContains('The participant a moves before or after another one.');

        $this->planner->move('ambush', 'gm', 'a', before: 'a');
    }

    public function testAMoveBesideItselfIsRefusedWhicheverSideNamesIt(): void
    {
        $this->prepared();

        foreach ([['a', 'b'], ['b', 'a']] as [$before, $after]) {
            try {
                $this->planner->move('ambush', 'gm', 'a', before: $before, after: $after);
                self::fail('A move beside the participant itself is refused.');
            } catch (EncounterRefused|InvalidEncounter $exception) {
                self::assertSame('a' === $before ? EncounterRefused::class : InvalidEncounter::class, $exception::class);
            }
        }
    }

    public function testAnEncounterIsForgottenWithoutObserver(): void
    {
        $this->prepared();

        new EncounterPlanner($this->store)->forget('ambush', 'gm');

        $this->expectException(EncounterNotFound::class);
        $this->planner->encounter('ambush');
    }

    public function testAMoveOnBothSidesIsRefused(): void
    {
        $this->prepared();

        $this->expectException(InvalidEncounter::class);
        $this->expectExceptionMessageIsOrContains('A participant comes before another one or after it, not both.');

        $this->planner->move('ambush', 'gm', 'a', before: 'b', after: 'c');
    }

    public function testAMoveOfAParticipantTheEncounterDoesNotHoldIsRefused(): void
    {
        $this->prepared();

        $this->expectException(EncounterRefused::class);
        $this->expectExceptionMessageIsOrContains('The encounter ambush has no participant z.');

        $this->planner->move('ambush', 'gm', 'z', before: 'a');
    }

    public function testOnlyTheGameMasterChangesTheEncounter(): void
    {
        $this->prepared();
        $calls = [
            fn (): mixed => $this->planner->retitle('ambush', 'player-hero', 'Mine'),
            fn (): mixed => $this->planner->add('ambush', 'player-hero', $this->foe('d')),
            fn (): mixed => $this->planner->change('ambush', 'player-hero', $this->foe('a', 2)),
            fn (): mixed => $this->planner->remove('ambush', 'player-hero', 'a'),
            fn (): mixed => $this->planner->move('ambush', 'player-hero', 'a', before: 'hero'),
            fn (): mixed => $this->planner->launch('ambush', 'player-hero'),
            function (): void {
                $this->planner->forget('ambush', 'player-hero');
            },
        ];

        foreach ($calls as $call) {
            try {
                $call();
                self::fail('Someone other than the game master changed the encounter.');
            } catch (EncounterRefused $exception) {
                self::assertSame('Only the game master changes the encounter ambush.', $exception->getMessage());
            }
        }

        self::assertSame(1, $this->planner->encounter('ambush')->revision);
        self::assertSame([], $this->observed->take());
        self::assertSame([], $this->tracker->rosters);
    }

    public function testTheCallerIsAnIdentifier(): void
    {
        $this->prepared();

        $this->expectException(InvalidEncounter::class);
        $this->expectExceptionMessageIsOrContains('The identifier of a member');

        $this->planner->forget('ambush', 'gm@example.com');
    }

    public function testTheEncounterIsAssessedByTheRulesOfItsSystem(): void
    {
        $this->prepared();

        $assessment = $this->planner->assess('ambush');

        self::assertSame('budget-game', $assessment->system);
        self::assertSame(225, $assessment->cost);
        self::assertSame('high', $assessment->difficulty);
    }

    public function testAnEncounterIsAssessedOnlyWithRulebooks(): void
    {
        $planner = new EncounterPlanner($this->store);
        $planner->prepare('ambush', 'gm', 'budget-game', null, [$this->hero()]);

        $this->expectException(UnknownSystem::class);
        $this->expectExceptionMessageIsOrContains('No rules of difficulty are declared for the game system budget-game.');

        $planner->assess('ambush');
    }

    public function testTheLaunchKeepsTheEncounterAndHandsItsRosterToTheTracker(): void
    {
        $this->planner->prepare('ambush', 'gm', 'budget-game', null, [$this->hero(), $this->foe('wolves', 3)]);
        $this->observed->take();

        $roster = $this->planner->launch('ambush', 'gm');

        self::assertSame(['hero', 'wolves.1', 'wolves.2', 'wolves.3'], array_map(static fn (RosterEntry $entry): string => $entry->combatant, $roster->entries));
        self::assertSame([$roster], $this->tracker->rosters);
        self::assertTrue($this->planner->encounter('ambush')->launched);
        self::assertSame(2, $this->planner->encounter('ambush')->revision);
        self::assertEquals([new EncounterLaunched('ambush', 4)], $this->observed->take());
        self::assertEquals($roster, $this->planner->roster('ambush'));
    }

    public function testTheLaunchWorksWithoutTracker(): void
    {
        $planner = new EncounterPlanner($this->store);
        $planner->prepare('ambush', 'gm', 'any-game', null, [$this->hero()]);

        self::assertSame('ambush', $planner->launch('ambush', 'gm')->encounter);
        self::assertTrue($planner->encounter('ambush')->launched);
    }

    public function testAnEncounterWithoutParticipantIsNotLaunched(): void
    {
        $this->planner->prepare('ambush', 'gm', 'budget-game');

        $this->expectException(EncounterRefused::class);
        $this->expectExceptionMessageIsOrContains('The encounter ambush has no participant to launch.');

        try {
            $this->planner->launch('ambush', 'gm');
        } finally {
            self::assertSame([], $this->tracker->rosters);
            self::assertFalse($this->planner->encounter('ambush')->launched);
        }
    }

    public function testALaunchedEncounterNoLongerChanges(): void
    {
        $this->prepared();
        $this->planner->launch('ambush', 'gm');
        $this->observed->take();
        $calls = [
            fn (): mixed => $this->planner->retitle('ambush', 'gm', 'Mine'),
            fn (): mixed => $this->planner->add('ambush', 'gm', $this->foe('d')),
            fn (): mixed => $this->planner->change('ambush', 'gm', $this->foe('a', 2)),
            fn (): mixed => $this->planner->remove('ambush', 'gm', 'a'),
            fn (): mixed => $this->planner->move('ambush', 'gm', 'a', before: 'hero'),
            fn (): mixed => $this->planner->launch('ambush', 'gm'),
        ];

        foreach ($calls as $call) {
            try {
                $call();
                self::fail('A launched encounter changed.');
            } catch (EncounterRefused $exception) {
                self::assertSame('The encounter ambush was launched and no longer changes.', $exception->getMessage());
            }
        }

        self::assertCount(1, $this->tracker->rosters);
        self::assertSame([], $this->observed->take());
        self::assertSame(225, $this->planner->assess('ambush')->cost);
    }

    public function testAnEncounterIsForgotten(): void
    {
        $this->prepared();
        $this->planner->launch('ambush', 'gm');
        $this->observed->take();

        $this->planner->forget('ambush', 'gm');

        self::assertNull($this->store->encounter('ambush'));
        self::assertEquals([new EncounterForgotten('ambush')], $this->observed->take());
    }

    public function testTheStoreRefusesAStaleEncounter(): void
    {
        $this->prepared();
        $stale = $this->planner->encounter('ambush');
        $this->planner->retitle('ambush', 'gm', 'Other');

        try {
            $this->store->save($stale->withTitle('Mine'));
            self::fail('A stale encounter was kept.');
        } catch (EncounterConflict $encounterConflict) {
            self::assertSame('The encounter ambush changed since it was read.', $encounterConflict->getMessage());
        }

        try {
            $this->store->remove($stale);
            self::fail('A stale encounter was removed.');
        } catch (EncounterConflict $encounterConflict) {
            self::assertSame('The encounter ambush changed since it was read.', $encounterConflict->getMessage());
        }

        self::assertSame('Other', $this->planner->encounter('ambush')->title);
    }

    public function testTheStoreRefusesAnEncounterItNeverKept(): void
    {
        $this->prepared();
        $kept = $this->planner->encounter('ambush');
        $this->store->remove($kept);

        $this->expectException(EncounterConflict::class);
        $this->expectExceptionMessageIsOrContains('The encounter ambush changed since it was read.');

        try {
            $this->store->save($kept);
        } finally {
            try {
                $this->store->remove($kept->withRevision(0));
                self::fail('An encounter never kept was removed.');
            } catch (EncounterConflict) {
                self::assertNull($this->store->encounter('ambush'));
            }
        }
    }
}
