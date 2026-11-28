<?php

declare(strict_types=1);

namespace Tilttek\InitiativeCards\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\CardDeck\Card;
use Tilttek\CardDeck\Deck;
use Tilttek\CardDeck\Face;
use Tilttek\InitiativeCards\Combatant;
use Tilttek\InitiativeCards\Encounter;
use Tilttek\InitiativeCards\Exception\EncounterConflict;
use Tilttek\InitiativeCards\Exception\InvalidEncounter;
use Tilttek\InitiativeCards\Internal\Identifier;
use Tilttek\InitiativeCards\StandardRanking;
use Tilttek\InitiativeCards\Store\InMemoryEncounterStore;

#[CoversClass(Combatant::class)]
#[CoversClass(Encounter::class)]
#[CoversClass(StandardRanking::class)]
#[CoversClass(InMemoryEncounterStore::class)]
#[CoversClass(Identifier::class)]
#[CoversClass(InvalidEncounter::class)]
#[CoversClass(EncounterConflict::class)]
final class ValuesTest extends TestCase
{
    public function testACombatantChangesOneThingAtATime(): void
    {
        $combatant = new Combatant('hero', 'p1', true, 2, false, 'AS', true);

        self::assertEquals(new Combatant('hero', 'p1', true, 2, false, 'KS', false), $combatant->withCard('KS'));
        self::assertEquals(new Combatant('hero', 'p1', true, 2, false, 'AS', false), $combatant->withActed(false));
        self::assertEquals(new Combatant('hero', 'p1', true, 2, true, 'AS', true), $combatant->withDefeated(true));
        self::assertEquals(new Combatant('hero', 'p1', false, 2, false, 'AS', true), $combatant->withHidden(false));
    }

    public function testACombatantIsPendingWithACardNotActedAndStanding(): void
    {
        self::assertTrue(new Combatant('hero', 'p1', card: 'AS')->isPending());
        self::assertFalse(new Combatant('hero', 'p1')->isPending());
        self::assertFalse(new Combatant('hero', 'p1', card: 'AS', acted: true)->isPending());
        self::assertFalse(new Combatant('hero', 'p1', defeated: true, card: 'AS')->isPending());
    }

    public function testACombatantDrawsOneToThreeCards(): void
    {
        self::assertSame(1, new Combatant('hero', 'p1', draws: 1)->draws);
        self::assertSame(3, new Combatant('hero', 'p1', draws: 3)->draws);
        foreach ([0, 4] as $draws) {
            try {
                new Combatant('hero', 'p1', draws: $draws);
                self::fail('Out of bounds.');
            } catch (InvalidEncounter $exception) {
                self::assertSame('A combatant draws 1 to 3 cards.', $exception->getMessage());
            }
        }
    }

    #[DataProvider('notIdentifiers')]
    public function testAnIdentifierHasItsShape(string $value): void
    {
        $this->expectException(InvalidEncounter::class);
        $this->expectExceptionMessageIsOrContains('The identifier of a combatant is 1 to 128 letters, digits, "_", ".", ":" or "-", starting with a letter.');
        new Combatant($value, 'p1');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notIdentifiers(): iterable
    {
        yield 'empty' => [''];
        yield 'a digit first' => ['1hero'];
        yield 'an address' => ['hero@example.org'];
        yield 'too long' => ['h'.str_repeat('e', 128)];
        yield 'a line after' => ["hero\n"];
    }

    public function testTheLongestIdentifierIsTaken(): void
    {
        $id = 'h'.str_repeat('e', 127);

        self::assertSame($id, new Combatant($id, 'p1')->id);
    }

    public function testTheControllerIsAMember(): void
    {
        $this->expectException(InvalidEncounter::class);
        $this->expectExceptionMessageIsOrContains('The identifier of a member');
        new Combatant('hero', '');
    }

    public function testAnEncounterHoldsEachCombatantOnce(): void
    {
        $this->expectException(InvalidEncounter::class);
        $this->expectExceptionMessageIsOrContains('The combatant a comes twice in the encounter.');
        new Encounter('fight', 'gm', $this->deck(), [new Combatant('a', 'p1'), new Combatant('a', 'p2')], []);
    }

    public function testAnEncounterHoldsAtMostAHundredCombatants(): void
    {
        $combatants = array_map(static fn (int $index): Combatant => new Combatant('c'.$index, 'p1'), range(1, 100));
        self::assertCount(100, new Encounter('fight', 'gm', $this->deck(), $combatants, [])->combatants);

        $this->expectException(InvalidEncounter::class);
        $this->expectExceptionMessageIsOrContains('An encounter holds at most 100 combatants.');
        new Encounter('fight', 'gm', $this->deck(), [...$combatants, new Combatant('c101', 'p1')], []);
    }

    public function testAnEncounterChecksItsIdentifiers(): void
    {
        try {
            new Encounter('', 'gm', $this->deck(), [], []);
            self::fail('An encounter identifier.');
        } catch (InvalidEncounter $invalidEncounter) {
            self::assertStringContainsString('of an encounter', $invalidEncounter->getMessage());
        }

        $this->expectException(InvalidEncounter::class);
        $this->expectExceptionMessageIsOrContains('of a member');
        new Encounter('fight', '', $this->deck(), [], []);
    }

    public function testTheRoundAndVersionAreNotNegative(): void
    {
        self::assertSame(0, new Encounter('fight', 'gm', $this->deck(), [], [], round: 0, version: 0)->round);
        foreach ([[-1, 0], [0, -1]] as [$round, $version]) {
            try {
                new Encounter('fight', 'gm', $this->deck(), [], [], round: $round, version: $version);
                self::fail('Negative.');
            } catch (InvalidEncounter $exception) {
                self::assertSame('The round and the version of an encounter are 0 or more.', $exception->getMessage());
            }
        }
    }

    public function testAnEncounterStartsBeforeTheFirstRound(): void
    {
        $encounter = new Encounter('fight', 'gm', $this->deck(), [], []);

        self::assertSame(0, $encounter->round);
        self::assertFalse($encounter->reshuffle);
        self::assertFalse($encounter->ended);
        self::assertSame(0, $encounter->version);
        self::assertSame([], $encounter->discard);
    }

    public function testAnEncounterFindsItsCombatantsAndTheCurrentOne(): void
    {
        $a = new Combatant('a', 'p1', card: 'AS', acted: true);
        $b = new Combatant('b', 'p2', defeated: true, card: 'KS');
        $c = new Combatant('c', 'p3', card: 'QS');
        $encounter = new Encounter('fight', 'gm', $this->deck(), [$a, $b, $c], [], round: 1);

        self::assertSame($b, $encounter->combatant('b'));
        self::assertNull($encounter->combatant('z'));
        self::assertSame($c, $encounter->current());
        self::assertNull($encounter->with([$a, $b])->current());
    }

    public function testAnEncounterChangesWhatItIsGiven(): void
    {
        $encounter = new Encounter('fight', 'gm', $this->deck(), [], ['AS'], ['KS'], 2, true, false, 4);

        $same = $encounter->with([]);
        self::assertEquals($encounter, $same);

        $changed = $encounter->with([new Combatant('a', 'p1')], ['QS'], [], 3, false, true);
        self::assertSame(['QS'], $changed->drawPile);
        self::assertSame([], $changed->discard);
        self::assertSame(3, $changed->round);
        self::assertFalse($changed->reshuffle);
        self::assertTrue($changed->ended);
        self::assertSame(4, $changed->version);
        self::assertSame('gm', $changed->gameMaster);
        self::assertSame($encounter->deck, $changed->deck);
        self::assertSame(['AS'], $encounter->withVersion(9)->drawPile);
        self::assertSame(9, $encounter->withVersion(9)->version);
        self::assertTrue($encounter->withVersion(9)->reshuffle);
        self::assertSame(2, $encounter->withVersion(9)->round);
        self::assertSame(['KS'], $encounter->withVersion(9)->discard);
    }

    public function testAnEndedEncounterHasNoCurrent(): void
    {
        $encounter = new Encounter('fight', 'gm', $this->deck(), [new Combatant('a', 'p1', card: 'AS')], [], round: 1, ended: true);

        self::assertNull($encounter->current());
        self::assertTrue($encounter->withVersion(2)->ended);
    }

    public function testTheStandardRankingPutsJokersThenRanksThenSuits(): void
    {
        $ranking = new StandardRanking();
        $faces = ['2C', 'JK', '10H', 'AS', 'AC', 'KD', 'QH', 'JS', '9S', '2S', 'AH', 'AD'];
        usort($faces, static fn (string $one, string $other): int => $ranking->compare(new Face($one), new Face($other)));

        self::assertSame(['JK', 'AS', 'AH', 'AD', 'AC', 'KD', 'QH', 'JS', '10H', '9S', '2S', '2C'], $faces);
        self::assertSame(0, $ranking->compare(new Face('JK'), new Face('JK')));
        self::assertSame(-1, $ranking->compare(new Face('JK'), new Face('AS')));
        self::assertSame(1, $ranking->compare(new Face('2C'), new Face('2D')));
    }

    public function testTheSuitsMayFollowAnotherOrder(): void
    {
        $ranking = new StandardRanking(['C', 'D', 'H', 'S']);

        self::assertSame(-1, $ranking->compare(new Face('5C'), new Face('5S')));
        self::assertSame(-1, $ranking->compare(new Face('6S'), new Face('5C')));
    }

    /**
     * @param list<string> $suits
     */
    #[DataProvider('notSuits')]
    public function testTheSuitsAreTheFourSuitsOnce(array $suits): void
    {
        $this->expectException(InvalidEncounter::class);
        $this->expectExceptionMessageIsOrContains('The order of the suits holds S, H, D and C, each once.');
        new StandardRanking($suits);
    }

    /**
     * @return iterable<string, array{list<string>}>
     */
    public static function notSuits(): iterable
    {
        yield 'three' => [['S', 'H', 'D']];
        yield 'twice' => [['S', 'H', 'D', 'D']];
        yield 'another' => [['S', 'H', 'D', 'X']];
        yield 'five' => [['S', 'H', 'D', 'C', 'C']];
    }

    public function testTheStandardRankingKnowsTheFacesOfTheDeck(): void
    {
        $ranking = new StandardRanking();

        foreach (['JK', 'AS', '10C', '2H', 'KD'] as $face) {
            self::assertTrue($ranking->ranks(new Face($face)), $face);
        }

        foreach (['S', '1S', '11H', 'AX', 'ASS', 'JKS', 'back'] as $face) {
            self::assertFalse($ranking->ranks(new Face($face)), $face);
        }
    }

    public function testTheStandardRankingRefusesAFaceItDoesNotKnow(): void
    {
        $this->expectException(InvalidEncounter::class);
        $this->expectExceptionMessageIsOrContains('The face back is not of the 52-card deck.');
        new StandardRanking()->compare(new Face('AS'), new Face('back'));
    }

    public function testAJokerReshufflesUnlessTold(): void
    {
        self::assertTrue(new StandardRanking()->reshuffles(new Face('JK')));
        self::assertFalse(new StandardRanking()->reshuffles(new Face('AS')));
        self::assertFalse(new StandardRanking(jokerShuffle: false)->reshuffles(new Face('JK')));
    }

    public function testTheStoreKeepsEachEncounterAtItsVersion(): void
    {
        $store = new InMemoryEncounterStore();
        $encounter = new Encounter('fight', 'gm', $this->deck(), [], []);

        self::assertNull($store->encounter('fight'));
        $kept = $store->save($encounter);
        self::assertSame(1, $kept->version);
        self::assertSame($kept, $store->encounter('fight'));
        self::assertSame(2, $store->save($kept)->version);

        $this->expectException(EncounterConflict::class);
        $this->expectExceptionMessageIsOrContains('The encounter fight changed since it was read.');
        $store->save($kept);
    }

    public function testTheStoreRefusesANewEncounterOfAKeptIdentifier(): void
    {
        $store = new InMemoryEncounterStore();
        $store->save(new Encounter('fight', 'gm', $this->deck(), [], []));

        $this->expectException(EncounterConflict::class);
        $store->save(new Encounter('fight', 'gm', $this->deck(), [], []));
    }

    private function deck(): Deck
    {
        return new Deck(Card::of('AS', 'AS'), Card::of('KS', 'KS'), Card::of('QS', 'QS'));
    }
}
