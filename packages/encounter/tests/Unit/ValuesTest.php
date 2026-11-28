<?php

declare(strict_types=1);

namespace Tilttek\Encounter\Tests\Unit;

use Closure;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\Encounter\Difficulty\Assessment;
use Tilttek\Encounter\Difficulty\Band;
use Tilttek\Encounter\Difficulty\BudgetRules;
use Tilttek\Encounter\Difficulty\Multiplier;
use Tilttek\Encounter\Encounter;
use Tilttek\Encounter\Exception\InvalidEncounter;
use Tilttek\Encounter\Exception\UnknownSystem;
use Tilttek\Encounter\Initiative\InitiativeRoster;
use Tilttek\Encounter\Initiative\RosterEntry;
use Tilttek\Encounter\Internal\Identifier;
use Tilttek\Encounter\Internal\Rating;
use Tilttek\Encounter\Internal\Text;
use Tilttek\Encounter\Kind;
use Tilttek\Encounter\Participant;
use Tilttek\Encounter\Rulebooks;
use Tilttek\Encounter\Tests\Unit\Fixture\Systems;
use Tilttek\RpgSystems\GameSystem;
use Tilttek\RpgSystems\Licence;
use Tilttek\RpgSystems\Provenance;
use Tilttek\RpgSystems\Translations;

#[CoversClass(Participant::class)]
#[CoversClass(Encounter::class)]
#[CoversClass(Band::class)]
#[CoversClass(Assessment::class)]
#[CoversClass(Multiplier::class)]
#[CoversClass(InitiativeRoster::class)]
#[CoversClass(RosterEntry::class)]
#[CoversClass(Rulebooks::class)]
#[CoversClass(Identifier::class)]
#[CoversClass(Rating::class)]
#[CoversClass(Text::class)]
#[CoversClass(InvalidEncounter::class)]
#[CoversClass(UnknownSystem::class)]
final class ValuesTest extends TestCase
{
    public function testAParticipantKeepsItsValues(): void
    {
        $participant = new Participant('goblins', Kind::Adversary, '1/4', 'gm-1', 3, true, -2, 'stat:goblin');

        self::assertSame('goblins', $participant->id);
        self::assertSame(Kind::Adversary, $participant->kind);
        self::assertSame('1/4', $participant->rating);
        self::assertSame('gm-1', $participant->controller);
        self::assertSame(3, $participant->quantity);
        self::assertTrue($participant->hidden);
        self::assertSame(-2, $participant->initiativeBonus);
        self::assertSame('stat:goblin', $participant->ref);
        self::assertSame(['goblins.1', 'goblins.2', 'goblins.3'], $participant->combatants());
    }

    public function testAParticipantOfOneCopyIsItsOwnCombatant(): void
    {
        $participant = new Participant('hero', Kind::Character, '3', 'player-1');

        self::assertSame(['hero'], $participant->combatants());
        self::assertSame(1, $participant->quantity);
        self::assertFalse($participant->hidden);
        self::assertNull($participant->initiativeBonus);
        self::assertNull($participant->ref);
    }

    public function testAParticipantTakesItsBoundsAtTheirLimits(): void
    {
        $participant = new Participant(str_repeat('a', 124), Kind::Ally, str_repeat('9', 16), str_repeat('m', 128), 50, false, 99, str_repeat('r', 128));

        self::assertCount(50, $participant->combatants());
        self::assertSame(str_repeat('a', 124).'.50', $participant->combatants()[49]);
        self::assertSame(-99, new Participant('a', Kind::Ally, '1', 'm', initiativeBonus: -99)->initiativeBonus);
    }

    /**
     * @return iterable<string, array{Closure(): Participant, string}>
     */
    public static function invalidParticipants(): iterable
    {
        yield 'an identifier too long' => [static fn (): Participant => new Participant(str_repeat('a', 125), Kind::Ally, '1', 'm'), 'The identifier of a participant is 1 to 124 letters'];
        yield 'an empty identifier' => [static fn (): Participant => new Participant('', Kind::Ally, '1', 'm'), 'The identifier of a participant'];
        yield 'an address as controller' => [static fn (): Participant => new Participant('a', Kind::Ally, '1', 'me@example.com'), 'The identifier of a member is 1 to 128'];
        yield 'a controller too long' => [static fn (): Participant => new Participant('a', Kind::Ally, '1', str_repeat('m', 129)), 'The identifier of a member'];
        yield 'a record that is no identifier' => [static fn (): Participant => new Participant('a', Kind::Ally, '1', 'm', ref: '1goblin'), 'The identifier of a record'];
        yield 'an empty rating' => [static fn (): Participant => new Participant('a', Kind::Ally, '', 'm'), 'The rating of the participant a is 1 to 16'];
        yield 'a rating too long' => [static fn (): Participant => new Participant('a', Kind::Ally, str_repeat('1', 17), 'm'), 'The rating of the participant a'];
        yield 'a rating with a space' => [static fn (): Participant => new Participant('a', Kind::Ally, '1 2', 'm'), 'The rating of the participant a'];
        yield 'a rating with a line feed' => [static fn (): Participant => new Participant('a', Kind::Ally, "1\n", 'm'), 'The rating of the participant a'];
        yield 'no copy' => [static fn (): Participant => new Participant('a', Kind::Adversary, '1', 'm', 0), 'A participant counts 1 to 50 copies.'];
        yield 'too many copies' => [static fn (): Participant => new Participant('a', Kind::Adversary, '1', 'm', 51), 'A participant counts 1 to 50 copies.'];
        yield 'two copies of a character' => [static fn (): Participant => new Participant('a', Kind::Character, '1', 'm', 2), 'The character a is one participant.'];
        yield 'a bonus too high' => [static fn (): Participant => new Participant('a', Kind::Ally, '1', 'm', initiativeBonus: 100), 'An initiative bonus is -99 to 99.'];
        yield 'a bonus too low' => [static fn (): Participant => new Participant('a', Kind::Ally, '1', 'm', initiativeBonus: -100), 'An initiative bonus is -99 to 99.'];
    }

    /**
     * @param Closure(): Participant $make
     */
    #[DataProvider('invalidParticipants')]
    public function testAParticipantOutOfItsShapeIsRefused(Closure $make, string $message): void
    {
        $this->expectException(InvalidEncounter::class);
        $this->expectExceptionMessageIsOrContains($message);

        $make();
    }

    public function testParticipantsAreTheSameOnlyInEveryValue(): void
    {
        $participant = new Participant('a', Kind::Adversary, '1', 'm', 2, true, 1, 'r');

        self::assertTrue($participant->sameAs(new Participant('a', Kind::Adversary, '1', 'm', 2, true, 1, 'r')));
        foreach ([
            new Participant('b', Kind::Adversary, '1', 'm', 2, true, 1, 'r'),
            new Participant('a', Kind::Ally, '1', 'm', 2, true, 1, 'r'),
            new Participant('a', Kind::Adversary, '2', 'm', 2, true, 1, 'r'),
            new Participant('a', Kind::Adversary, '1', 'n', 2, true, 1, 'r'),
            new Participant('a', Kind::Adversary, '1', 'm', 3, true, 1, 'r'),
            new Participant('a', Kind::Adversary, '1', 'm', 2, false, 1, 'r'),
            new Participant('a', Kind::Adversary, '1', 'm', 2, true, null, 'r'),
            new Participant('a', Kind::Adversary, '1', 'm', 2, true, 1),
        ] as $other) {
            self::assertFalse($participant->sameAs($other));
        }
    }

    public function testAnEncounterKeepsItsValues(): void
    {
        $hero = new Participant('hero', Kind::Character, '2', 'player-1');
        $goblins = new Participant('goblins', Kind::Adversary, '1/4', 'gm-1', 3);
        $dog = new Participant('dog', Kind::Ally, '1/4', 'player-1');
        $encounter = new Encounter('ambush', 'gm-1', 'srd-5.2', [$hero, $goblins, $dog], '  The ambush  ', true, 4);

        self::assertSame('ambush', $encounter->id);
        self::assertSame('gm-1', $encounter->gameMaster);
        self::assertSame('srd-5.2', $encounter->system);
        self::assertSame('The ambush', $encounter->title);
        self::assertTrue($encounter->launched);
        self::assertSame(4, $encounter->revision);
        self::assertSame([$hero, $goblins, $dog], $encounter->participants);
        self::assertSame($goblins, $encounter->participant('goblins'));
        self::assertNull($encounter->participant('orc'));
        self::assertSame([$hero], $encounter->of(Kind::Character));
        self::assertSame([$goblins], $encounter->of(Kind::Adversary));
        self::assertSame([$dog], $encounter->of(Kind::Ally));
        self::assertSame(5, $encounter->combatants());
    }

    public function testAnEncounterChangesIntoANewOne(): void
    {
        $hero = new Participant('hero', Kind::Character, '2', 'player-1');
        $encounter = new Encounter('ambush', 'gm-1', 'srd-5.2', [], 'Title', false, 2);

        $with = $encounter->with([$hero]);
        self::assertSame([$hero], $with->participants);
        self::assertSame(['ambush', 'gm-1', 'srd-5.2', 'Title', false, 2], [$with->id, $with->gameMaster, $with->system, $with->title, $with->launched, $with->revision]);

        $retitled = $with->withTitle(null);
        self::assertNull($retitled->title);
        self::assertSame([[$hero], false, 2], [$retitled->participants, $retitled->launched, $retitled->revision]);

        $launched = $with->launch();
        self::assertTrue($launched->launched);
        self::assertSame([[$hero], 'Title', 2], [$launched->participants, $launched->title, $launched->revision]);

        $revised = $launched->withRevision(7);
        self::assertSame([[$hero], 'Title', true, 7], [$revised->participants, $revised->title, $revised->launched, $revised->revision]);
        self::assertSame(0, new Encounter('a', 'm', 's')->revision);
        self::assertFalse(new Encounter('a', 'm', 's')->launched);
        self::assertNull(new Encounter('a', 'm', 's')->title);
    }

    public function testAnEncounterTakesItsBoundsAtTheirLimits(): void
    {
        $participants = [];
        for ($i = 0; $i < 100; ++$i) {
            $participants[] = new Participant('p'.$i, Kind::Adversary, '1', 'm');
        }

        self::assertSame(100, new Encounter('a', 'm', 's', $participants)->combatants());
        self::assertSame(100, new Encounter('a', 'm', 's', [new Participant('a', Kind::Adversary, '1', 'm', 50), new Participant('b', Kind::Adversary, '1', 'm', 50)])->combatants());
        self::assertSame(str_repeat('é', 200), new Encounter('a', 'm', 's', [], str_repeat('é', 200))->title);
    }

    /**
     * @return iterable<string, array{Closure(): Encounter, string}>
     */
    public static function invalidEncounters(): iterable
    {
        $one = static fn (string $id, int $quantity = 1): Participant => new Participant($id, Kind::Adversary, '1', 'm', $quantity);

        yield 'an identifier' => [static fn (): Encounter => new Encounter('1', 'm', 's'), 'The identifier of an encounter'];
        yield 'a game master' => [static fn (): Encounter => new Encounter('a', 'a@b', 's'), 'The identifier of a member'];
        yield 'a system' => [static fn (): Encounter => new Encounter('a', 'm', 'srd 5'), 'The identifier of a game system'];
        yield 'an empty title' => [static fn (): Encounter => new Encounter('a', 'm', 's', [], '   '), 'The title of an encounter is empty.'];
        yield 'a title too long' => [static fn (): Encounter => new Encounter('a', 'm', 's', [], str_repeat('é', 201)), 'The title of an encounter is 200 characters at most.'];
        yield 'a title of two lines' => [static fn (): Encounter => new Encounter('a', 'm', 's', [], "One\nTwo"), 'The title of an encounter is one line without control characters.'];
        yield 'a title with a tab' => [static fn (): Encounter => new Encounter('a', 'm', 's', [], "One\tTwo"), 'The title of an encounter is one line'];
        yield 'a title reordered' => [static fn (): Encounter => new Encounter('a', 'm', 's', [], "One\u{202E}Two"), 'The title of an encounter is one line'];
        yield 'a title of an isolate' => [static fn (): Encounter => new Encounter('a', 'm', 's', [], "One\u{2069}"), 'The title of an encounter is one line'];
        yield 'a title of DEL' => [static fn (): Encounter => new Encounter('a', 'm', 's', [], "One\x7F"), 'The title of an encounter is one line'];
        yield 'a title not UTF-8' => [static fn (): Encounter => new Encounter('a', 'm', 's', [], "\xFF"), 'The title of an encounter is not valid UTF-8.'];
        yield 'too many participants' => [static fn (): Encounter => new Encounter('a', 'm', 's', array_map(static fn (int $i): Participant => $one('p'.$i), range(0, 100))), 'An encounter holds at most 100 participants.'];
        yield 'too many combatants' => [static fn (): Encounter => new Encounter('a', 'm', 's', [$one('a', 50), $one('b', 50), $one('c')]), 'An encounter counts at most 100 combatants.'];
        yield 'a participant twice' => [static fn (): Encounter => new Encounter('a', 'm', 's', [$one('a'), $one('a')]), 'The participant a comes twice in the encounter.'];
        yield 'a copy named as another participant' => [static fn (): Encounter => new Encounter('a', 'm', 's', [$one('g', 2), $one('g.2')]), 'The combatant g.2 comes twice in the encounter.'];
        yield 'a participant named as a later copy' => [static fn (): Encounter => new Encounter('a', 'm', 's', [$one('g.1'), $one('g', 2)]), 'The combatant g.1 comes twice in the encounter.'];
        yield 'a revision below 0' => [static fn (): Encounter => new Encounter('a', 'm', 's', [], null, false, -1), 'The revision of an encounter is 0 or more.'];
    }

    /**
     * @param Closure(): Encounter $make
     */
    #[DataProvider('invalidEncounters')]
    public function testAnEncounterOutOfItsShapeIsRefused(Closure $make, string $message): void
    {
        $this->expectException(InvalidEncounter::class);
        $this->expectExceptionMessageIsOrContains($message);

        $make();
    }

    public function testAnAssessmentFindsTheLastBandItsCostMeets(): void
    {
        $bands = [new Band('low', 50), new Band('moderate', 75), new Band('high', 100)];

        self::assertNull(new Assessment('s', 49, $bands, 1, 1)->difficulty);
        self::assertSame('low', new Assessment('s', 50, $bands, 1, 1)->difficulty);
        self::assertSame('low', new Assessment('s', 74, $bands, 1, 1)->difficulty);
        self::assertSame('moderate', new Assessment('s', 75, $bands, 1, 1)->difficulty);
        self::assertSame('high', new Assessment('s', 1000, $bands, 1, 1)->difficulty);
        self::assertSame('high', new Assessment('s', 0, [new Band('a', 0), new Band('high', 0)], 1, 0)->difficulty);
    }

    public function testAnAssessmentTellsWhatRemainsToEachBand(): void
    {
        $assessment = new Assessment('s', 60, [new Band('low', 50), new Band('high', 100)], 2, 3);

        self::assertSame('s', $assessment->system);
        self::assertSame(60, $assessment->cost);
        self::assertSame(2, $assessment->characters);
        self::assertSame(3, $assessment->adversaries);
        self::assertSame(100, $assessment->threshold('high'));
        self::assertSame(50, $assessment->threshold('low'));
        self::assertTrue($assessment->reached('low'));
        self::assertFalse($assessment->reached('high'));
        self::assertTrue(new Assessment('s', 100, [new Band('high', 100)], 1, 1)->reached('high'));
        self::assertSame(0, $assessment->remainingTo('low'));
        self::assertSame(40, $assessment->remainingTo('high'));
        self::assertSame([
            'system' => 's',
            'cost' => 60,
            'difficulty' => 'low',
            'characters' => 2,
            'adversaries' => 3,
            'bands' => [['key' => 'low', 'threshold' => 50], ['key' => 'high', 'threshold' => 100]],
        ], $assessment->toArray());
    }

    public function testAnAssessmentRefusesABandItDoesNotDeclare(): void
    {
        $this->expectException(InvalidEncounter::class);
        $this->expectExceptionMessageIsOrContains('The rules of s declare no band deadly.');

        new Assessment('s', 60, [new Band('low', 50)], 1, 1)->remainingTo('deadly');
    }

    public function testAnAssessmentTakesTwelveBands(): void
    {
        $bands = array_map(static fn (int $i): Band => new Band('b'.$i, $i), range(1, 12));

        self::assertSame('b12', new Assessment('s', 12, $bands, 1, 0)->difficulty);
    }

    /**
     * @return iterable<string, array{Closure(): mixed, string}>
     */
    public static function invalidAssessments(): iterable
    {
        yield 'a system' => [static fn (): Assessment => new Assessment('', 0, [new Band('a', 0)], 0, 0), 'The identifier of a game system'];
        yield 'a cost below 0' => [static fn (): Assessment => new Assessment('s', -1, [new Band('a', 0)], 0, 0), 'The cost, the characters and the adversaries of an assessment are 0 or more.'];
        yield 'characters below 0' => [static fn (): Assessment => new Assessment('s', 0, [new Band('a', 0)], -1, 0), 'The cost, the characters'];
        yield 'adversaries below 0' => [static fn (): Assessment => new Assessment('s', 0, [new Band('a', 0)], 0, -1), 'The cost, the characters'];
        yield 'no band' => [static fn (): Assessment => new Assessment('s', 0, [], 0, 0), 'An assessment has 1 to 12 bands of difficulty.'];
        yield 'thirteen bands' => [static fn (): Assessment => new Assessment('s', 0, array_map(static fn (int $i): Band => new Band('b'.$i, $i), range(1, 13)), 0, 0), 'An assessment has 1 to 12 bands of difficulty.'];
        yield 'a band twice' => [static fn (): Assessment => new Assessment('s', 0, [new Band('a', 0), new Band('a', 1)], 0, 0), 'The band a comes twice.'];
        yield 'bands out of order' => [static fn (): Assessment => new Assessment('s', 0, [new Band('a', 2), new Band('b', 1)], 0, 0), 'The threshold of the band b is under the one before it.'];
        yield 'a band key' => [static fn (): Band => new Band('1', 0), 'The identifier of a band of difficulty'];
        yield 'a threshold below 0' => [static fn (): Band => new Band('a', -1), 'The threshold of the band a is 0 or more.'];
        yield 'a multiplier from 0' => [static fn (): Multiplier => new Multiplier(0, 100), 'A multiplier holds from 1 adversary or more.'];
        yield 'a multiplier of 0 percent' => [static fn (): Multiplier => new Multiplier(1, 0), 'A multiplier is 1 to 1000 percent.'];
        yield 'a multiplier over 1000 percent' => [static fn (): Multiplier => new Multiplier(1, 1001), 'A multiplier is 1 to 1000 percent.'];
    }

    /**
     * @param Closure(): mixed $make
     */
    #[DataProvider('invalidAssessments')]
    public function testADifficultyValueOutOfItsShapeIsRefused(Closure $make, string $message): void
    {
        $this->expectException(InvalidEncounter::class);
        $this->expectExceptionMessageIsOrContains($message);

        $make();
    }

    public function testAMultiplierKeepsItsBounds(): void
    {
        $multiplier = new Multiplier(1, 1000);

        self::assertSame(1, $multiplier->from);
        self::assertSame(1000, $multiplier->percent);
        self::assertSame(1, new Multiplier(3, 1)->percent);
    }

    public function testTheRosterMakesEachCopyACombatantInOrder(): void
    {
        $encounter = new Encounter('ambush', 'gm-1', 'srd-5.2', [
            new Participant('hero', Kind::Character, '2', 'player-1', initiativeBonus: 3, ref: 'sheet:7'),
            new Participant('wolves', Kind::Adversary, '1', 'gm-1', 2, true),
        ]);

        $roster = InitiativeRoster::of($encounter);

        self::assertSame([
            'encounter' => 'ambush',
            'gameMaster' => 'gm-1',
            'system' => 'srd-5.2',
            'entries' => [
                ['combatant' => 'hero', 'participant' => 'hero', 'kind' => 'character', 'controller' => 'player-1', 'hidden' => false, 'initiativeBonus' => 3, 'ref' => 'sheet:7'],
                ['combatant' => 'wolves.1', 'participant' => 'wolves', 'kind' => 'adversary', 'controller' => 'gm-1', 'hidden' => true, 'initiativeBonus' => null, 'ref' => null],
                ['combatant' => 'wolves.2', 'participant' => 'wolves', 'kind' => 'adversary', 'controller' => 'gm-1', 'hidden' => true, 'initiativeBonus' => null, 'ref' => null],
            ],
        ], $roster->toArray());
        self::assertSame('wolves.2', $roster->entries[2]->combatant);
        self::assertSame(Kind::Adversary, $roster->entries[2]->kind);
    }

    public function testTheRulebooksFindTheRulesOfASystem(): void
    {
        $budget = Systems::budget();
        $level = Systems::level();
        $rulebooks = new Rulebooks($budget, $level);
        $system = new GameSystem('level-game', Translations::of(['en' => 'Level game']), new Provenance('Own', Licence::Owned));

        self::assertSame($budget, $rulebooks->for('budget-game'));
        self::assertSame($level, $rulebooks->for($system));
        self::assertTrue($rulebooks->has('budget-game'));
        self::assertTrue($rulebooks->has($system));
        self::assertFalse($rulebooks->has('other'));
        self::assertFalse(new Rulebooks()->has($system));
    }

    public function testTheRulebooksRefuseASystemWithoutRules(): void
    {
        $this->expectException(UnknownSystem::class);
        $this->expectExceptionMessageIsOrContains('No rules of difficulty are declared for the game system other.');

        new Rulebooks(Systems::budget())->for('other');
    }

    public function testTheRulebooksRefuseTwoRulesForOneSystem(): void
    {
        $this->expectException(InvalidEncounter::class);
        $this->expectExceptionMessageIsOrContains('Two rules of difficulty are declared for the game system budget-game.');

        new Rulebooks(Systems::budget(), Systems::level(), Systems::budget());
    }

    public function testTheRulebooksTakeAThousandSystems(): void
    {
        $rules = array_map(static fn (int $i): BudgetRules => new BudgetRules('s'.$i, ['low'], ['1' => ['low' => 1]], ['1' => 1]), range(1, 1000));

        self::assertSame($rules[999], new Rulebooks(...$rules)->for('s1000'));
    }

    public function testTheRulebooksTakeAThousandSystemsAtMost(): void
    {
        $rules = array_fill(0, 1001, Systems::budget());

        $this->expectException(InvalidEncounter::class);
        $this->expectExceptionMessageIsOrContains('Rules of difficulty are declared for at most 1000 game systems.');

        new Rulebooks(...$rules);
    }
}
