<?php

declare(strict_types=1);

namespace Tilttek\InitiativeCards\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Random\Engine\Mt19937;
use Random\Randomizer;
use Tilttek\CardDeck\Card;
use Tilttek\CardDeck\CardId;
use Tilttek\CardDeck\Deck;
use Tilttek\CardDeck\Face;
use Tilttek\CardDeck\Shuffler;
use Tilttek\CardDeck\StandardDeck;
use Tilttek\InitiativeCards\Combatant;
use Tilttek\InitiativeCards\Encounter;
use Tilttek\InitiativeCards\Event\CombatantDefeated;
use Tilttek\InitiativeCards\Event\CombatantHidden;
use Tilttek\InitiativeCards\Event\CombatantJoined;
use Tilttek\InitiativeCards\Event\CombatantLeft;
use Tilttek\InitiativeCards\Event\CombatantRevealed;
use Tilttek\InitiativeCards\Event\CombatantRevived;
use Tilttek\InitiativeCards\Event\DeckReshuffled;
use Tilttek\InitiativeCards\Event\EncounterEnded;
use Tilttek\InitiativeCards\Event\EncounterStarted;
use Tilttek\InitiativeCards\Event\RoundDealt;
use Tilttek\InitiativeCards\Event\RoundEnded;
use Tilttek\InitiativeCards\Event\TurnEnded;
use Tilttek\InitiativeCards\Event\TurnStarted;
use Tilttek\InitiativeCards\Exception\EncounterNotFound;
use Tilttek\InitiativeCards\Exception\InitiativeRefused;
use Tilttek\InitiativeCards\Exception\InvalidEncounter;
use Tilttek\InitiativeCards\InitiativeTracker;
use Tilttek\InitiativeCards\StandardRanking;
use Tilttek\InitiativeCards\Store\InMemoryEncounterStore;
use Tilttek\InitiativeCards\Tests\Unit\Fixture\Observed;
use Tilttek\InitiativeCards\View\InitiativeView;
use Tilttek\InitiativeCards\View\Slot;

#[CoversClass(InitiativeTracker::class)]
#[CoversClass(EncounterNotFound::class)]
#[CoversClass(InitiativeRefused::class)]
#[CoversClass(InitiativeView::class)]
#[CoversClass(Slot::class)]
#[CoversClass(EncounterStarted::class)]
#[CoversClass(EncounterEnded::class)]
#[CoversClass(RoundDealt::class)]
#[CoversClass(RoundEnded::class)]
#[CoversClass(DeckReshuffled::class)]
#[CoversClass(TurnStarted::class)]
#[CoversClass(TurnEnded::class)]
#[CoversClass(CombatantJoined::class)]
#[CoversClass(CombatantLeft::class)]
#[CoversClass(CombatantDefeated::class)]
#[CoversClass(CombatantRevived::class)]
#[CoversClass(CombatantRevealed::class)]
#[CoversClass(CombatantHidden::class)]
final class InitiativeTrackerTest extends TestCase
{
    private const string GM = 'gm';

    private InMemoryEncounterStore $store;

    private Observed $observed;

    private InitiativeTracker $tracker;

    protected function setUp(): void
    {
        $this->store = new InMemoryEncounterStore();
        $this->observed = new Observed();
        $this->tracker = new InitiativeTracker($this->store, new StandardRanking(), $this->observed, new Randomizer(new Mt19937(7)));
    }

    public function testStartShufflesTheWholeDeckAndDealsNothing(): void
    {
        $encounter = $this->tracker->start('fight', self::GM, StandardDeck::create(jokers: 2), [new Combatant('hero', 'p1')]);

        self::assertSame(1, $encounter->version);
        self::assertSame(0, $encounter->round);
        self::assertCount(54, $encounter->drawPile);
        $sorted = $encounter->drawPile;
        sort($sorted);
        $expected = array_map(static fn (Card $card): string => $card->id->value, StandardDeck::create(jokers: 2)->cards());
        sort($expected);
        self::assertSame($expected, $sorted);
        self::assertNotSame(array_map(static fn (Card $card): string => $card->id->value, StandardDeck::create(jokers: 2)->cards()), $encounter->drawPile);
        self::assertNull($encounter->current());
        self::assertEquals([new EncounterStarted('fight')], $this->observed->take());
        self::assertSame($encounter, $this->tracker->encounter('fight'));
    }

    public function testStartShufflesWithTheRandomizerGiven(): void
    {
        $deck = StandardDeck::create();
        $ids = array_map(static fn (Card $card): string => $card->id->value, $deck->cards());

        $encounter = $this->tracker->start('fight', self::GM, $deck);

        self::assertSame(Shuffler::shuffle($ids, new Randomizer(new Mt19937(7))), $encounter->drawPile);
    }

    public function testNoObserverIsNeededToTellTheEvents(): void
    {
        $tracker = new InitiativeTracker($this->store, randomizer: new Randomizer(new Mt19937(7)));
        $tracker->start('fight', self::GM, $this->deck(['5H', 'KS']), [$this->hero('a'), $this->hero('b')]);

        self::assertSame(1, $tracker->deal('fight', self::GM)->round);
    }

    public function testTheDefeatedNeedNoCardsToDeal(): void
    {
        $this->seed([], [$this->hero('a'), $this->hero('b')->withDefeated(true)], ['KS']);

        $encounter = $this->tracker->deal('fight', self::GM);

        self::assertEquals([new DeckReshuffled('fight'), new RoundDealt('fight', 1), new TurnStarted('fight', 'a', false)], $this->observed->take());
        self::assertSame(['KS', null], $this->cards($encounter));
    }

    public function testACombatantJoinsAfterTwoOthersBeforeTheFirstRound(): void
    {
        $this->seed(['AS'], [$this->hero('a'), $this->hero('b')]);

        $encounter = $this->tracker->join('fight', $this->hero('c'), self::GM);

        self::assertSame(['a', 'b', 'c'], $this->ids($encounter));
    }

    public function testACombatantJoiningInARoundWithoutAJokerLeavesTheDeckAsItIs(): void
    {
        $this->dealt();

        $encounter = $this->tracker->join('fight', $this->hero('d'), self::GM);

        self::assertFalse($encounter->reshuffle);
    }

    public function testACombatantLeavingAddsItsCardToTheDiscardPile(): void
    {
        $this->seed(['2D'], [new Combatant('a', 'p-a', card: 'KS')], ['9C']);

        $encounter = $this->tracker->leave('fight', 'a', self::GM);

        self::assertSame(['9C', 'KS'], $encounter->discard);
    }

    public function testACombatantJoiningWithATiedCardWaitsAfterTheOthers(): void
    {
        $this->store->save(new Encounter('fight', self::GM, $this->jokers(3), [new Combatant('x', 'p-x', card: 'J1'), new Combatant('y', 'p-y', card: 'J2')], ['J3'], [], 1));

        $encounter = $this->tracker->join('fight', $this->hero('c'), self::GM);

        self::assertSame(['x', 'y', 'c'], $this->ids($encounter));
    }

    public function testAReviveOfACombatantWithoutACardLeavesItNotActed(): void
    {
        $this->store->save(new Encounter('fight', self::GM, $this->deck(['KS', '5H']), [
            new Combatant('x', 'p-x', card: 'KS', acted: true),
            new Combatant('t', 'p-t', defeated: true),
            new Combatant('y', 'p-y', card: '5H'),
        ], [], [], 1));

        $encounter = $this->tracker->revive('fight', 't', self::GM);

        self::assertFalse($encounter->combatant('t')?->acted);
    }

    public function testAReviveBeforeCombatantsWhoWaitLeavesItNotActed(): void
    {
        $this->store->save(new Encounter('fight', self::GM, $this->deck(['KS', 'QD', '5H']), [
            new Combatant('x', 'p-x', card: 'KS'),
            new Combatant('t', 'p-t', defeated: true, card: 'QD'),
            new Combatant('y', 'p-y', card: '5H'),
        ], [], [], 1));

        $encounter = $this->tracker->revive('fight', 't', self::GM);

        self::assertFalse($encounter->combatant('t')?->acted);
        self::assertSame('x', $encounter->current()?->id);
    }

    public function testTheNamedCombatantMovesUpAndTheOthersFollow(): void
    {
        $this->seed(['KS', 'QD', 'JH', '2C'], [$this->hero('a'), $this->hero('b'), $this->hero('c'), $this->hero('d')]);
        $this->tracker->deal('fight', self::GM);

        $encounter = $this->tracker->endTurn('fight', 'a', self::GM, 'c');

        self::assertSame(['a', 'c', 'b', 'd'], $this->ids($encounter));
    }

    public function testStartRefusesAnIdentifierTaken(): void
    {
        $this->tracker->start('fight', self::GM, $this->deck(['AS']));

        $this->expectException(InitiativeRefused::class);
        $this->expectExceptionMessageIsOrContains('An encounter has the identifier fight.');
        $this->tracker->start('fight', self::GM, $this->deck(['AS']));
    }

    public function testStartRefusesAFaceTheRankingDoesNotKnow(): void
    {
        $this->expectException(InvalidEncounter::class);
        $this->expectExceptionMessageIsOrContains('The ranking does not know the face 1S.');
        $this->tracker->start('fight', self::GM, new Deck(Card::of('a', 'AS'), Card::of('b', '1S')));
    }

    public function testStartChecksEveryFaceOfACard(): void
    {
        $this->expectException(InvalidEncounter::class);
        $this->expectExceptionMessageIsOrContains('The ranking does not know the face XX.');
        $this->tracker->start('fight', self::GM, new Deck(new Card(new CardId('a'), [new Face('AS'), new Face('XX')], new Face('back'))));
    }

    public function testStartRefusesACombatantHoldingACard(): void
    {
        $this->expectException(InvalidEncounter::class);
        $this->expectExceptionMessageIsOrContains('A combatant joins without a card.');
        $this->tracker->start('fight', self::GM, $this->deck(['AS']), [new Combatant('hero', 'p1', card: 'AS')]);
    }

    public function testStartRefusesACombatantWhoActed(): void
    {
        $this->expectException(InvalidEncounter::class);
        $this->tracker->start('fight', self::GM, $this->deck(['AS']), [new Combatant('hero', 'p1', acted: true)]);
    }

    public function testAnUnknownEncounterIsNotFound(): void
    {
        $this->expectException(EncounterNotFound::class);
        $this->expectExceptionMessageIsOrContains('No encounter has the identifier nope.');
        $this->tracker->encounter('nope');
    }

    public function testDealOrdersTheTurnsByTheCards(): void
    {
        $this->seed(['5H', 'KS', '5S', 'QD'], [$this->hero('a'), $this->hero('b'), $this->hero('c'), $this->hero('d')]);

        $encounter = $this->tracker->deal('fight', self::GM);

        self::assertSame(['b', 'd', 'c', 'a'], $this->ids($encounter));
        self::assertSame(['KS', 'QD', '5S', '5H'], $this->cards($encounter));
        self::assertSame(1, $encounter->round);
        self::assertSame([], $encounter->drawPile);
        self::assertFalse($encounter->reshuffle);
        self::assertSame('b', $encounter->current()?->id);
        self::assertEquals([new RoundDealt('fight', 1), new TurnStarted('fight', 'b', false)], $this->observed->take());
    }

    public function testTiesKeepThePreviousOrder(): void
    {
        $tracker = new InitiativeTracker($this->store, new StandardRanking(jokerShuffle: false), $this->observed);
        $this->seed(['J1', 'J2', 'J3'], [$this->hero('a'), $this->hero('b'), $this->hero('c')], deck: $this->jokers(3));

        self::assertSame(['a', 'b', 'c'], $this->ids($tracker->deal('fight', self::GM)));
    }

    public function testTheDefeatedDrawNothingAndComeLast(): void
    {
        $this->seed(['2C', 'AS'], [$this->hero('a')->withDefeated(true), $this->hero('b'), $this->hero('c')]);

        $encounter = $this->tracker->deal('fight', self::GM);

        self::assertSame(['c', 'b', 'a'], $this->ids($encounter));
        self::assertSame(['AS', '2C', null], $this->cards($encounter));
    }

    public function testACombatantDrawingSeveralCardsKeepsTheBest(): void
    {
        $this->seed(['3H', 'KH', '7C', '9D'], [new Combatant('a', 'p1', draws: 3), $this->hero('b')]);

        $encounter = $this->tracker->deal('fight', self::GM);

        self::assertSame(['a', 'b'], $this->ids($encounter));
        self::assertSame(['KH', '9D'], $this->cards($encounter));
        self::assertSame(['7C', '3H'], $encounter->discard);
    }

    public function testTheNextDealDiscardsTheCardsHeld(): void
    {
        $this->seed(['5H', 'KS', '2D', '3D', '9C'], [$this->hero('a'), $this->hero('b')]);
        $this->tracker->deal('fight', self::GM);
        $this->observed->take();

        $encounter = $this->tracker->deal('fight', self::GM);

        self::assertSame(2, $encounter->round);
        self::assertSame(['KS', '5H'], $encounter->discard);
        self::assertSame(['9C'], $encounter->drawPile);
        self::assertSame(['a', 'b'], $this->ids($encounter));
        self::assertSame(['3D', '2D'], $this->cards($encounter));
        self::assertEquals([new RoundDealt('fight', 2), new TurnStarted('fight', 'a', false)], $this->observed->take());
    }

    public function testAJokerReshufflesBeforeTheNextRound(): void
    {
        $this->seed(['JK1', 'KS', '2D', '3D', '9C'], [$this->hero('a'), $this->hero('b')], deck: $this->deck(['KS', '2D', '3D', '9C'], 1));

        $first = $this->tracker->deal('fight', self::GM);
        self::assertTrue($first->reshuffle);
        self::assertSame(['a', 'b'], $this->ids($first));
        $this->observed->take();

        $second = $this->tracker->deal('fight', self::GM);

        self::assertSame([], $second->discard);
        self::assertCount(3, $second->drawPile);
        self::assertSame(5, \count($second->drawPile) + 2);
        self::assertEquals(new DeckReshuffled('fight'), $this->observed->take()[0]);
    }

    public function testAJokerDealtSecondStillReshuffles(): void
    {
        $this->seed(['KS', 'JK1', '2D'], [$this->hero('a'), $this->hero('b')], deck: $this->deck(['KS', '2D'], 1));

        self::assertTrue($this->tracker->deal('fight', self::GM)->reshuffle);
    }

    public function testAShortDrawPileIsReshuffledWithTheDiscards(): void
    {
        $this->seed(['KS'], [$this->hero('a'), $this->hero('b')], discard: ['2D', '3D']);

        $encounter = $this->tracker->deal('fight', self::GM);

        self::assertSame([], $encounter->discard);
        self::assertCount(1, $encounter->drawPile);
        self::assertEquals(new DeckReshuffled('fight'), $this->observed->take()[0]);
    }

    public function testAPileJustLongEnoughIsNotReshuffled(): void
    {
        $this->seed(['KS', 'QS'], [$this->hero('a'), $this->hero('b')], discard: ['2D']);

        $encounter = $this->tracker->deal('fight', self::GM);

        self::assertSame(['2D'], $encounter->discard);
        self::assertInstanceOf(RoundDealt::class, $this->observed->take()[0]);
    }

    public function testADeckTooSmallIsRefused(): void
    {
        $this->seed(['KS'], [$this->hero('a'), new Combatant('b', 'p2', draws: 2)], discard: ['2D']);

        $this->expectException(InitiativeRefused::class);
        $this->expectExceptionMessageIsOrContains('The deck holds 2 cards, and the combatants draw 3.');
        $this->tracker->deal('fight', self::GM);
    }

    public function testOnlyTheGameMasterDeals(): void
    {
        $this->seed(['KS'], [$this->hero('a')]);

        $this->expectException(InitiativeRefused::class);
        $this->expectExceptionMessageIsOrContains('Only the game master changes the encounter.');
        $this->tracker->deal('fight', 'p1');
    }

    public function testAnEndedEncounterChangesNoMore(): void
    {
        $this->seed(['KS'], [$this->hero('a')]);
        $ended = $this->tracker->end('fight', self::GM);

        self::assertTrue($ended->ended);
        self::assertEquals([new EncounterEnded('fight')], $this->observed->take());
        $this->expectException(InitiativeRefused::class);
        $this->expectExceptionMessageIsOrContains('The encounter fight has ended.');
        $this->tracker->deal('fight', self::GM);
    }

    public function testAnEndedEncounterHasNoCurrentTurn(): void
    {
        $this->seed(['KS'], [$this->hero('a')]);
        $this->tracker->deal('fight', self::GM);

        self::assertNull($this->tracker->end('fight', self::GM)->current());
    }

    public function testAPlayerEndsTheTurnOfTheirCombatant(): void
    {
        $this->dealt();

        $encounter = $this->tracker->endTurn('fight', 'b', 'p-b');

        self::assertTrue($encounter->combatant('b')?->acted);
        self::assertSame('c', $encounter->current()?->id);
        self::assertEquals([new TurnEnded('fight', 'b'), new TurnStarted('fight', 'c', false)], $this->observed->take());
    }

    public function testTheGameMasterEndsAnyTurn(): void
    {
        $this->dealt();

        self::assertSame('c', $this->tracker->endTurn('fight', 'b', self::GM)->current()?->id);
    }

    public function testAnotherPlayerEndsNoTurn(): void
    {
        $this->dealt();

        $this->expectException(InitiativeRefused::class);
        $this->expectExceptionMessageIsOrContains('Only the controller of a combatant or the game master ends its turn.');
        $this->tracker->endTurn('fight', 'b', 'p-c');
    }

    public function testOnlyTheCombatantHoldingTheTurnEndsIt(): void
    {
        $this->dealt();

        $this->expectException(InitiativeRefused::class);
        $this->expectExceptionMessageIsOrContains('The combatant c does not hold the turn.');
        $this->tracker->endTurn('fight', 'c', self::GM);
    }

    public function testNoTurnEndsBeforeTheFirstRound(): void
    {
        $this->seed(['KS'], [$this->hero('a')]);

        $this->expectException(InitiativeRefused::class);
        $this->tracker->endTurn('fight', 'a', self::GM);
    }

    public function testTheLastTurnEndsTheRound(): void
    {
        $this->dealt();
        $this->tracker->endTurn('fight', 'b', self::GM);
        $this->tracker->endTurn('fight', 'c', self::GM);

        $this->observed->take();

        $encounter = $this->tracker->endTurn('fight', 'a', 'p-a');

        self::assertNull($encounter->current());
        self::assertEquals([new TurnEnded('fight', 'a'), new RoundEnded('fight', 1)], $this->observed->take());
    }

    public function testThePlayerNamesWhoGoesNext(): void
    {
        $this->seed(['KS', 'QS', 'JS', '10S'], [$this->hero('a'), $this->hero('b'), $this->hero('c'), $this->hero('d')]);
        $this->tracker->deal('fight', self::GM);
        $this->observed->take();

        $encounter = $this->tracker->endTurn('fight', 'a', 'p-a', 'd');

        self::assertSame(['a', 'd', 'b', 'c'], $this->ids($encounter));
        self::assertSame('d', $encounter->current()?->id);
        self::assertEquals([new TurnEnded('fight', 'a'), new TurnStarted('fight', 'd', false)], $this->observed->take());
    }

    public function testTheNextNamedWhenTheCurrentIsNotFirst(): void
    {
        $this->seed(['KS', 'QS', 'JS', '10S'], [$this->hero('a'), $this->hero('b'), $this->hero('c'), $this->hero('d')]);
        $this->tracker->deal('fight', self::GM);
        $this->tracker->endTurn('fight', 'a', self::GM);

        self::assertSame(['a', 'b', 'd', 'c'], $this->ids($this->tracker->endTurn('fight', 'b', self::GM, 'd')));
    }

    public function testTheNextNamedHasNotActed(): void
    {
        $this->dealt();
        $this->tracker->endTurn('fight', 'b', self::GM);

        $this->expectException(InitiativeRefused::class);
        $this->expectExceptionMessageIsOrContains('The combatant b is not waiting for its turn.');
        $this->tracker->endTurn('fight', 'c', self::GM, 'b');
    }

    public function testTheNextNamedIsAnother(): void
    {
        $this->dealt();

        $this->expectException(InitiativeRefused::class);
        $this->expectExceptionMessageIsOrContains('The combatant b is not waiting for its turn.');
        $this->tracker->endTurn('fight', 'b', self::GM, 'b');
    }

    public function testTheNextNamedIsOneOfTheEncounter(): void
    {
        $this->dealt();

        $this->expectException(EncounterNotFound::class);
        $this->expectExceptionMessageIsOrContains('The encounter fight has no combatant zed.');
        $this->tracker->endTurn('fight', 'b', self::GM, 'zed');
    }

    public function testACombatantJoinsBeforeTheFirstRoundLast(): void
    {
        $this->seed(['KS'], [$this->hero('a')]);

        $encounter = $this->tracker->join('fight', new Combatant('orc', self::GM, hidden: true), self::GM);

        self::assertSame(['a', 'orc'], $this->ids($encounter));
        self::assertEquals([new CombatantJoined('fight', 'orc', true)], $this->observed->take());
    }

    public function testACombatantJoiningARoundWaitsAfterTheCurrentByItsCard(): void
    {
        $this->seed(['KS', '9S', '5S', 'AS', '7S'], [$this->hero('a'), $this->hero('b'), $this->hero('c')]);
        $this->tracker->deal('fight', self::GM);
        $this->observed->take();

        $encounter = $this->tracker->join('fight', $this->hero('d'), self::GM);

        self::assertSame(['a', 'd', 'b', 'c'], $this->ids($encounter));
        self::assertSame(['KS', 'AS', '9S', '5S'], $this->cards($encounter));
        self::assertSame(['7S'], $encounter->drawPile);
        self::assertSame('a', $encounter->current()?->id);
        self::assertEquals([new CombatantJoined('fight', 'd', false)], $this->observed->take());

        $later = $this->tracker->join('fight', $this->hero('e'), self::GM);
        self::assertSame(['a', 'd', 'b', 'e', 'c'], $this->ids($later));
    }

    public function testACombatantJoiningWithTheLowestCardGoesLast(): void
    {
        $this->seed(['KS', '9S', '2C'], [$this->hero('a'), $this->hero('b')]);
        $this->tracker->deal('fight', self::GM);

        self::assertSame(['a', 'b', 'd'], $this->ids($this->tracker->join('fight', $this->hero('d'), self::GM)));
    }

    public function testACombatantJoiningKeepsTheBestOfItsCards(): void
    {
        $this->seed(['KS', '2S', '9S', '3H'], [$this->hero('a')]);
        $this->tracker->deal('fight', self::GM);

        $encounter = $this->tracker->join('fight', new Combatant('d', 'p-d', draws: 2), self::GM);

        self::assertSame('9S', $encounter->combatant('d')?->card);
        self::assertSame(['2S'], $encounter->discard);
        self::assertSame(['3H'], $encounter->drawPile);
    }

    public function testACombatantJoiningWithAJokerReshufflesAfterTheRound(): void
    {
        $this->seed(['KS', 'JK1'], [$this->hero('a')], deck: $this->deck(['KS'], 1));
        $this->tracker->deal('fight', self::GM);

        self::assertTrue($this->tracker->join('fight', $this->hero('d'), self::GM)->reshuffle);
    }

    public function testAReshuffleFlagStaysWhenACombatantJoins(): void
    {
        $this->seed(['JK1', 'KS'], [$this->hero('a')], deck: $this->deck(['KS'], 1));
        $this->tracker->deal('fight', self::GM);

        self::assertTrue($this->tracker->join('fight', $this->hero('d'), self::GM)->reshuffle);
    }

    public function testACombatantJoiningAnEmptyPileReshufflesTheDiscards(): void
    {
        $this->seed(['KS'], [$this->hero('a')], discard: ['QS']);
        $this->tracker->deal('fight', self::GM);
        $this->observed->take();

        $encounter = $this->tracker->join('fight', $this->hero('d'), self::GM);

        self::assertSame('QS', $encounter->combatant('d')?->card);
        self::assertSame([], $encounter->discard);
        self::assertEquals([new DeckReshuffled('fight'), new CombatantJoined('fight', 'd', false)], $this->observed->take());
    }

    public function testACombatantJoiningWithExactlyTheCardsLeftDrawsThem(): void
    {
        $this->seed(['KS', 'QS'], [$this->hero('a')], discard: ['2S']);
        $this->tracker->deal('fight', self::GM);

        $encounter = $this->tracker->join('fight', $this->hero('d'), self::GM);

        self::assertSame(['2S'], $encounter->discard);
    }

    public function testACombatantFindingTooFewCardsIsRefused(): void
    {
        $this->seed(['KS', 'QS'], [$this->hero('a')]);
        $this->tracker->deal('fight', self::GM);

        $this->expectException(InitiativeRefused::class);
        $this->expectExceptionMessageIsOrContains('The deck holds 1 cards to draw, and the combatant draws 2.');
        $this->tracker->join('fight', new Combatant('d', 'p-d', draws: 2), self::GM);
    }

    public function testACombatantJoiningAfterTheRoundTakesTheTurn(): void
    {
        $this->seed(['KS', 'QS'], [$this->hero('a')]);
        $this->tracker->deal('fight', self::GM);
        $this->tracker->endTurn('fight', 'a', self::GM);

        $this->observed->take();

        $encounter = $this->tracker->join('fight', $this->hero('d'), self::GM);

        self::assertSame('d', $encounter->current()?->id);
        self::assertEquals([new CombatantJoined('fight', 'd', false), new TurnStarted('fight', 'd', false)], $this->observed->take());
    }

    public function testADefeatedCombatantJoinsWithoutACard(): void
    {
        $this->seed(['KS', 'QS'], [$this->hero('a')]);
        $this->tracker->deal('fight', self::GM);

        $encounter = $this->tracker->join('fight', $this->hero('d')->withDefeated(true), self::GM);

        self::assertSame(['a', 'd'], $this->ids($encounter));
        self::assertNull($encounter->combatant('d')?->card);
        self::assertSame(['QS'], $encounter->drawPile);
    }

    public function testAJoiningCombatantHoldsNoCard(): void
    {
        $this->seed(['KS'], [$this->hero('a')]);

        $this->expectException(InvalidEncounter::class);
        $this->tracker->join('fight', new Combatant('d', 'p-d', card: 'KS'), self::GM);
    }

    public function testAJoiningCombatantHasNotActed(): void
    {
        $this->seed(['KS'], [$this->hero('a')]);

        $this->expectException(InvalidEncounter::class);
        $this->tracker->join('fight', new Combatant('d', 'p-d', acted: true), self::GM);
    }

    public function testAJoiningCombatantHasANewIdentifier(): void
    {
        $this->seed(['KS'], [$this->hero('a')]);

        $this->expectException(InitiativeRefused::class);
        $this->expectExceptionMessageIsOrContains('The encounter has a combatant a.');
        $this->tracker->join('fight', $this->hero('a'), self::GM);
    }

    public function testACombatantLeavingDiscardsItsCardAndPassesTheTurn(): void
    {
        $this->dealt();

        $encounter = $this->tracker->leave('fight', 'b', self::GM);

        self::assertSame(['c', 'a'], $this->ids($encounter));
        self::assertSame(['KS'], $encounter->discard);
        self::assertEquals([new CombatantLeft('fight', 'b'), new TurnStarted('fight', 'c', false)], $this->observed->take());
    }

    public function testACombatantLeavingOutOfTurnPassesNothing(): void
    {
        $this->dealt();

        $this->tracker->leave('fight', 'a', self::GM);

        self::assertEquals([new CombatantLeft('fight', 'a')], $this->observed->take());
    }

    public function testACombatantWithoutACardLeavesNothingToDiscard(): void
    {
        $this->seed(['KS'], [$this->hero('a')]);

        self::assertSame([], $this->tracker->leave('fight', 'a', self::GM)->discard);
    }

    public function testAnUnknownCombatantCannotLeave(): void
    {
        $this->dealt();

        $this->expectException(EncounterNotFound::class);
        $this->tracker->leave('fight', 'zed', self::GM);
    }

    public function testADefeatedCombatantIsSkipped(): void
    {
        $this->dealt();

        $encounter = $this->tracker->defeat('fight', 'b', self::GM);

        self::assertTrue($encounter->combatant('b')?->defeated);
        self::assertSame('KS', $encounter->combatant('b')->card);
        self::assertSame('c', $encounter->current()?->id);
        self::assertEquals([new CombatantDefeated('fight', 'b'), new TurnStarted('fight', 'c', false)], $this->observed->take());
        self::assertSame($encounter, $this->tracker->defeat('fight', 'b', self::GM));
        self::assertSame([], $this->observed->take());
    }

    public function testDefeatingACombatantOutOfTurnPassesNothing(): void
    {
        $this->dealt();

        $this->tracker->defeat('fight', 'a', self::GM);

        self::assertEquals([new CombatantDefeated('fight', 'a')], $this->observed->take());
    }

    public function testDefeatingTheLastStandingEndsTheRound(): void
    {
        $this->seed(['KS'], [$this->hero('a')]);
        $this->tracker->deal('fight', self::GM);
        $this->observed->take();

        $this->tracker->defeat('fight', 'a', self::GM);

        self::assertEquals([new CombatantDefeated('fight', 'a'), new RoundEnded('fight', 1)], $this->observed->take());
    }

    public function testAnUnknownCombatantCannotBeDefeated(): void
    {
        $this->dealt();

        $this->expectException(EncounterNotFound::class);
        $this->tracker->defeat('fight', 'zed', self::GM);
    }

    public function testACombatantRevivedAheadOfTheTurnWaitsForItsCard(): void
    {
        $this->dealt();
        $this->tracker->defeat('fight', 'a', self::GM);
        $this->observed->take();

        $encounter = $this->tracker->revive('fight', 'a', self::GM);

        self::assertFalse($encounter->combatant('a')?->defeated);
        self::assertFalse($encounter->combatant('a')->acted);
        self::assertSame('b', $encounter->current()?->id);
        self::assertEquals([new CombatantRevived('fight', 'a')], $this->observed->take());
    }

    public function testACombatantRevivedBehindTheTurnWaitsForTheNextRound(): void
    {
        $this->dealt();
        $this->tracker->defeat('fight', 'b', self::GM);

        $encounter = $this->tracker->revive('fight', 'b', self::GM);

        self::assertTrue($encounter->combatant('b')?->acted);
        self::assertSame('c', $encounter->current()?->id);
    }

    public function testACombatantRevivedAfterTheRoundWaitsForTheNextOne(): void
    {
        $this->dealt();
        $this->tracker->defeat('fight', 'c', self::GM);
        $this->tracker->endTurn('fight', 'b', self::GM);
        $this->tracker->endTurn('fight', 'a', self::GM);

        $this->observed->take();

        $encounter = $this->tracker->revive('fight', 'c', self::GM);

        self::assertTrue($encounter->combatant('c')?->acted);
        self::assertNull($encounter->current());
        self::assertEquals([new CombatantRevived('fight', 'c')], $this->observed->take());
    }

    public function testTheLastCardRevivedAfterTheOthersTakesTheTurn(): void
    {
        $this->dealt();
        $this->tracker->defeat('fight', 'a', self::GM);
        $this->tracker->endTurn('fight', 'b', self::GM);
        $this->tracker->endTurn('fight', 'c', self::GM);

        $this->observed->take();

        $encounter = $this->tracker->revive('fight', 'a', self::GM);

        self::assertSame('a', $encounter->current()?->id);
        self::assertEquals([new CombatantRevived('fight', 'a'), new TurnStarted('fight', 'a', false)], $this->observed->take());
    }

    public function testACombatantRevivedWhoActedStaysActed(): void
    {
        $this->dealt();
        $this->tracker->endTurn('fight', 'b', self::GM);
        $this->tracker->defeat('fight', 'b', self::GM);
        $this->tracker->endTurn('fight', 'c', self::GM);
        $this->tracker->defeat('fight', 'a', self::GM);

        $this->observed->take();

        $encounter = $this->tracker->revive('fight', 'b', self::GM);

        self::assertTrue($encounter->combatant('b')?->acted);
    }

    public function testACombatantRevivedWithoutACardTakesNoTurn(): void
    {
        $this->seed(['KS'], [$this->hero('a')->withDefeated(true)]);

        $encounter = $this->tracker->revive('fight', 'a', self::GM);

        self::assertFalse($encounter->combatant('a')?->acted);
        self::assertNull($encounter->current());
    }

    public function testTheOnlyCombatantRevivedTakesTheTurn(): void
    {
        $this->seed(['KS', 'QS'], [$this->hero('a'), $this->hero('b')]);
        $this->tracker->deal('fight', self::GM);
        $this->tracker->defeat('fight', 'a', self::GM);
        $this->tracker->defeat('fight', 'b', self::GM);

        $this->observed->take();

        $encounter = $this->tracker->revive('fight', 'b', self::GM);

        self::assertSame('b', $encounter->current()?->id);
        self::assertEquals([new CombatantRevived('fight', 'b'), new TurnStarted('fight', 'b', false)], $this->observed->take());
    }

    public function testRevivingACombatantStandingChangesNothing(): void
    {
        $encounter = $this->dealt();

        self::assertEquals($encounter, $this->tracker->revive('fight', 'a', self::GM));
        self::assertSame([], $this->observed->take());
    }

    public function testAnUnknownCombatantCannotBeRevived(): void
    {
        $this->dealt();

        $this->expectException(EncounterNotFound::class);
        $this->tracker->revive('fight', 'zed', self::GM);
    }

    public function testACombatantIsHiddenThenRevealed(): void
    {
        $this->dealt();

        $hidden = $this->tracker->reveal('fight', 'c', self::GM, false);
        self::assertTrue($hidden->combatant('c')?->hidden);
        self::assertSame($hidden, $this->tracker->reveal('fight', 'c', self::GM, false));

        $shown = $this->tracker->reveal('fight', 'c', self::GM);
        self::assertFalse($shown->combatant('c')?->hidden);
        self::assertSame($shown, $this->tracker->reveal('fight', 'c', self::GM));

        self::assertEquals([new CombatantHidden('fight', 'c'), new CombatantRevealed('fight', 'c')], $this->observed->take());
    }

    public function testAnUnknownCombatantCannotBeRevealed(): void
    {
        $this->dealt();

        $this->expectException(EncounterNotFound::class);
        $this->tracker->reveal('fight', 'zed', self::GM);
    }

    public function testAHiddenCombatantsTurnIsToldHidden(): void
    {
        $this->seed(['KS', 'QS'], [new Combatant('orc', self::GM, hidden: true), $this->hero('a')]);

        $this->tracker->deal('fight', self::GM);

        self::assertEquals([new RoundDealt('fight', 1), new TurnStarted('fight', 'orc', true)], $this->observed->take());
    }

    public function testThePlayersDoNotSeeTheHidden(): void
    {
        $this->seed(['KS', 'QS', '2H'], [new Combatant('orc', self::GM, hidden: true), $this->hero('a'), $this->hero('b')->withDefeated(true)]);
        $this->tracker->deal('fight', self::GM);

        $player = $this->tracker->view('fight', 'p-a');
        $spectator = $this->tracker->view('fight');
        $master = $this->tracker->view('fight', self::GM);

        self::assertEquals($player, $spectator);
        self::assertSame([
            'encounter' => 'fight',
            'round' => 1,
            'slots' => [
                ['combatant' => 'a', 'face' => 'QS', 'acted' => false, 'defeated' => false, 'current' => false, 'hidden' => false],
                ['combatant' => 'b', 'face' => null, 'acted' => false, 'defeated' => true, 'current' => false, 'hidden' => false],
            ],
            'drawPile' => 1,
            'ended' => false,
            'version' => 2,
        ], $player->toArray());
        self::assertEquals(
            [
                new Slot('orc', 'KS', false, false, true, true),
                new Slot('a', 'QS', false, false, false, false),
                new Slot('b', null, false, true, false, false),
            ],
            $master->slots,
        );
    }

    public function testTheViewShowsTheTurnAndWhoActed(): void
    {
        $this->dealt();
        $this->tracker->endTurn('fight', 'b', self::GM);

        $view = $this->tracker->view('fight', 'p-a');

        self::assertEquals(
            [new Slot('b', 'KS', true, false, false, false), new Slot('c', 'QD', false, false, true, false), new Slot('a', '5H', false, false, false, false)],
            $view->slots,
        );
    }

    /**
     * Three combatants dealt KS (b), QD (c), 5H (a), and the events taken.
     */
    private function dealt(): Encounter
    {
        $this->seed(['5H', 'KS', 'QD', '2C'], [$this->hero('a'), $this->hero('b'), $this->hero('c')]);
        $encounter = $this->tracker->deal('fight', self::GM);
        $this->observed->take();

        return $encounter;
    }

    private function hero(string $id): Combatant
    {
        return new Combatant($id, 'p-'.$id);
    }

    /**
     * Keeps an encounter whose draw pile is in a known order, every card of it in its deck.
     *
     * @param list<string>    $pile
     * @param list<Combatant> $combatants
     * @param list<string>    $discard
     */
    private function seed(array $pile, array $combatants, array $discard = [], ?Deck $deck = null): void
    {
        $this->store->save(new Encounter('fight', self::GM, $deck ?? $this->deck([...$pile, ...$discard]), $combatants, $pile, $discard));
    }

    /**
     * A deck of cards whose identifier is their face, and jokers `JK1` onwards.
     *
     * @param list<string> $faces
     */
    private function deck(array $faces, int $jokers = 0): Deck
    {
        $cards = array_map(static fn (string $face): Card => Card::of($face, $face), $faces);
        for ($joker = 1; $joker <= $jokers; ++$joker) {
            $cards[] = Card::of('JK'.$joker, 'JK');
        }

        return new Deck(...$cards);
    }

    private function jokers(int $count): Deck
    {
        $cards = [];
        for ($joker = 1; $joker <= $count; ++$joker) {
            $cards[] = Card::of('J'.$joker, 'JK');
        }

        return new Deck(...$cards);
    }

    /**
     * @return list<string>
     */
    private function ids(Encounter $encounter): array
    {
        return array_map(static fn (Combatant $combatant): string => $combatant->id, $encounter->combatants);
    }

    /**
     * @return list<?string>
     */
    private function cards(Encounter $encounter): array
    {
        return array_map(static fn (Combatant $combatant): ?string => $combatant->card, $encounter->combatants);
    }
}
