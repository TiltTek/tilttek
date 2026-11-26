<?php

declare(strict_types=1);

namespace Tilttek\Tournament\Tests\Unit\Elimination;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\Tournament\Elimination\Bracket;
use Tilttek\Tournament\Elimination\BracketMatch;
use Tilttek\Tournament\Elimination\BracketState;
use Tilttek\Tournament\Elimination\MatchState;
use Tilttek\Tournament\Elimination\SeedOrder;
use Tilttek\Tournament\Elimination\Slot;
use Tilttek\Tournament\Elimination\SlotKind;
use Tilttek\Tournament\Exception\InvalidTournament;
use Tilttek\Tournament\Outcome;
use Tilttek\Tournament\Pairing;
use Tilttek\Tournament\PairingReason;
use Tilttek\Tournament\ReasonKind;
use Tilttek\Tournament\Result;

#[CoversClass(SeedOrder::class)]
#[CoversClass(Slot::class)]
#[CoversClass(SlotKind::class)]
#[CoversClass(Bracket::class)]
#[CoversClass(BracketMatch::class)]
#[CoversClass(BracketState::class)]
#[CoversClass(MatchState::class)]
final class BracketTest extends TestCase
{
    public function testTheSeedOrderMeetsTheBestAsLateAsPossible(): void
    {
        self::assertSame([1, 2], SeedOrder::of(2));
        self::assertSame([1, 4, 2, 3], SeedOrder::of(4));
        self::assertSame([1, 8, 4, 5, 2, 7, 3, 6], SeedOrder::of(8));
        self::assertSame([1, 16, 8, 9, 4, 13, 5, 12, 2, 15, 7, 10, 3, 14, 6, 11], SeedOrder::of(16));
        self::assertCount(256, SeedOrder::of(256));
    }

    public function testTheSizeOfABracketIsAPowerOfTwo(): void
    {
        foreach ([0, 1, 3, 6, 512] as $size) {
            try {
                SeedOrder::of($size);
                self::fail('The size '.$size.' is refused.');
            } catch (InvalidTournament $exception) {
                self::assertSame('A bracket has a power of 2 places, 2 to 256.', $exception->getMessage());
            }
        }
    }

    public function testTheSizeHoldsTheParticipants(): void
    {
        self::assertSame(2, SeedOrder::sizeFor(1));
        self::assertSame(2, SeedOrder::sizeFor(2));
        self::assertSame(4, SeedOrder::sizeFor(3));
        self::assertSame(8, SeedOrder::sizeFor(5));
        self::assertSame(8, SeedOrder::sizeFor(8));
        self::assertSame(16, SeedOrder::sizeFor(9));
    }

    public function testSlotsShowWhatFillsThem(): void
    {
        self::assertSame('alice', Slot::participant('alice')->label());
        self::assertSame(SlotKind::Participant, Slot::participant('alice')->kind);
        self::assertSame('pools/A#2', Slot::qualifier('pools/A', 2)->label());
        self::assertSame(2, Slot::qualifier('pools/A', 2)->rank);
        self::assertSame('pools/A', Slot::qualifier('pools/A', 1)->from);
        self::assertSame('W3', Slot::winnerOf(3)->label());
        self::assertSame(3, Slot::winnerOf(3)->match);
        self::assertSame('bye', Slot::bye()->label());
        self::assertSame(SlotKind::Bye, Slot::bye()->kind);
    }

    public function testARankIsOneOrMore(): void
    {
        self::assertSame(1, Slot::qualifier('s', 1)->rank);
        $this->expectException(InvalidTournament::class);
        $this->expectExceptionMessageIs('The rank of a qualifier is 1 or more.');
        Slot::qualifier('s', 0);
    }

    public function testAMatchIsNumberedFromOne(): void
    {
        self::assertSame(1, Slot::winnerOf(1)->match);
        $this->expectException(InvalidTournament::class);
        $this->expectExceptionMessageIs('A match of a bracket is numbered from 1.');
        Slot::winnerOf(0);
    }

    public function testAParticipantSlotIsAnIdentifier(): void
    {
        $this->expectException(InvalidTournament::class);
        Slot::participant('1');
    }

    public function testFiveSeedsGiveByesToTheThreeFirst(): void
    {
        $bracket = Bracket::seeded($this->slots(5));

        self::assertSame(3, $bracket->rounds);
        self::assertCount(7, $bracket->matches);
        self::assertSame([['s1', 'bye'], ['s4', 's5'], ['s2', 'bye'], ['s3', 'bye']], $this->labels($bracket->round(1)));
        self::assertSame([['W1', 'W2'], ['W3', 'W4']], $this->labels($bracket->round(2)));
        self::assertSame([['W5', 'W6']], $this->labels($bracket->round(3)));
        self::assertSame([1, 8], [$bracket->matches[0]->firstSeed, $bracket->matches[0]->secondSeed]);
        self::assertNull($bracket->matches[4]->firstSeed);
        self::assertSame(7, $bracket->final()->id);
        self::assertSame(5, $bracket->next(1)?->id);
        self::assertSame(5, $bracket->next(2)?->id);
        self::assertSame(6, $bracket->next(4)?->id);
        self::assertSame(7, $bracket->next(6)?->id);
        self::assertNull($bracket->next(7));
        self::assertSame(3, $bracket->match(3)?->id);
        self::assertNull($bracket->match(8));
        self::assertNull($bracket->match(0));
        self::assertSame([], $bracket->round(4));
    }

    public function testTwoSeedsPlayTheFinalAtOnce(): void
    {
        $bracket = Bracket::seeded($this->slots(2));

        self::assertSame(1, $bracket->rounds);
        self::assertSame([['s1', 's2']], $this->labels($bracket->matches));
    }

    public function testABracketHasTwoToTwoHundredFiftySixSeeds(): void
    {
        self::assertCount(255, Bracket::seeded($this->slots(256))->matches);

        foreach ([1, 257] as $count) {
            try {
                Bracket::seeded($this->slots($count));
                self::fail($count.' seeds are refused.');
            } catch (InvalidTournament $exception) {
                self::assertSame('A bracket has 2 to 256 seeds.', $exception->getMessage());
            }
        }
    }

    public function testTheStateFollowsTheResults(): void
    {
        $bracket = Bracket::seeded($this->slots(5));
        $state = BracketState::of($bracket, results: [2 => Result::secondWins()]);

        self::assertSame('s1', $state->state(1)?->winner);
        self::assertTrue($state->state(1)->walkover);
        self::assertTrue($state->state(1)->secondEmpty);
        self::assertFalse($state->state(1)->firstEmpty);
        self::assertFalse($state->state(1)->isPlayable());
        self::assertSame('s5', $state->state(2)?->winner);
        self::assertFalse($state->state(2)->walkover);
        self::assertSame('s4', $state->state(2)->loser());
        self::assertSame(['s1', 's5'], [$state->state(5)?->first, $state->state(5)?->second]);
        self::assertNotNull($state->state(5));
        self::assertTrue($state->state(5)->isPlayable());
        self::assertFalse($state->state(5)->decided);
        self::assertNull($state->state(5)->loser());
        self::assertNull($state->state(7)?->first);
        self::assertNull($state->champion());
        self::assertSame([], $state->podium());
        self::assertNull($state->state(8));
        self::assertEquals([
            new Pairing(1, 's1', null, [new PairingReason(ReasonKind::Bye, 's1')], rest: true),
            new Pairing(2, 's4', 's5', [new PairingReason(ReasonKind::Seeded, 's4', 's5'), new PairingReason(ReasonKind::Seeded, 's5', 's4')]),
            new Pairing(3, 's2', null, [new PairingReason(ReasonKind::Bye, 's2')], rest: true),
            new Pairing(4, 's3', null, [new PairingReason(ReasonKind::Bye, 's3')], rest: true),
        ], $state->pairings(1));
        self::assertSame([5, 6], array_map(static fn (Pairing $pairing): int => $pairing->table, $state->pairings(2)));
        self::assertSame([], $state->pairings(3), 'The final waits for its participants.');
        self::assertSame(['s1' => 2, 's4' => 1, 's5' => 2, 's2' => 2, 's3' => 2], $state->reached());
    }

    public function testThePodiumOnceTheFinalIsDecided(): void
    {
        $bracket = Bracket::seeded($this->slots(5));
        $state = BracketState::of($bracket, results: [
            2 => Result::firstWins(),
            5 => Result::secondWins(),
            6 => new Result(Outcome::SecondForfeits),
            7 => Result::secondWins(),
        ]);

        self::assertSame('s2', $state->champion());
        self::assertSame(['s2', 's4', 's1', 's3'], $state->podium());
        self::assertSame(4, $state->reached()['s2']);
        self::assertSame(3, $state->reached()['s4']);
        self::assertSame(2, $state->reached()['s1']);
    }

    public function testAMatchBothSidesLostEmptiesThePlaceAfter(): void
    {
        $bracket = Bracket::seeded($this->slots(4));
        $state = BracketState::of($bracket, results: [1 => new Result(Outcome::BothForfeit), 2 => Result::firstWins()]);

        self::assertTrue($state->state(1)?->decided);
        self::assertNull($state->state(1)->winner);
        self::assertNull($state->state(1)->loser());
        self::assertTrue($state->state(3)?->firstEmpty);
        self::assertSame('s2', $state->state(3)->winner);
        self::assertTrue($state->state(3)->walkover);
        self::assertSame('s2', $state->champion());
        self::assertSame(['s2', 's3'], $state->podium(), 'No finalist: the loser of the other semi-final is third.');
    }

    public function testBothPlacesEmptyDecideTheMatchWithoutAWinner(): void
    {
        $bracket = Bracket::seeded($this->slots(4));
        $state = BracketState::of($bracket, results: [1 => new Result(Outcome::BothForfeit), 2 => new Result(Outcome::BothForfeit)]);

        self::assertTrue($state->state(3)?->decided);
        self::assertNull($state->state(3)->winner);
        self::assertFalse($state->state(3)->walkover);
        self::assertNull($state->champion());
        self::assertSame([], $state->podium());
        self::assertSame([], $state->pairings(2));
    }

    public function testAWithdrawalBeforePlayingIsAWalkover(): void
    {
        $bracket = Bracket::seeded($this->slots(4));
        $state = BracketState::of($bracket, results: [1 => Result::firstWins()], withdrawn: ['s1' => true, 's3' => true]);

        self::assertSame('s1', $state->state(1)?->winner, 'A result kept stands.');
        self::assertSame('s2', $state->state(2)?->winner);
        self::assertTrue($state->state(2)->walkover);
        self::assertSame('s2', $state->state(3)?->winner);
        self::assertTrue($state->state(3)->firstEmpty);
    }

    public function testAWithdrawalOfTheSecondBeforePlaying(): void
    {
        $state = BracketState::of(Bracket::seeded($this->slots(2)), withdrawn: ['s2' => true]);

        self::assertSame('s1', $state->champion());
        self::assertTrue($state->state(1)?->secondEmpty);
    }

    public function testADrawDecidesNothing(): void
    {
        $state = BracketState::of(Bracket::seeded($this->slots(2)), results: [1 => Result::draw()]);

        self::assertFalse($state->state(1)?->decided);
        self::assertNull($state->champion());
    }

    public function testQualifiersFillTheirPlacesOnceKnown(): void
    {
        $bracket = Bracket::seeded([Slot::qualifier('swiss', 1), Slot::qualifier('swiss', 2), Slot::qualifier('swiss', 3)]);
        $waiting = BracketState::of($bracket);

        self::assertNull($waiting->state(1)?->first);
        self::assertNotNull($waiting->state(1));
        self::assertFalse($waiting->state(1)->decided, 'A qualifier not known yet against a bye waits.');
        self::assertSame([], $waiting->pairings(1));

        $known = BracketState::of($bracket, ['swiss#1' => 'alice', 'swiss#2' => 'bob', 'swiss#3' => 'carol']);
        self::assertSame('alice', $known->state(1)?->winner);
        self::assertSame(['bob', 'carol'], [$known->state(2)?->first, $known->state(2)?->second]);
    }

    /**
     * @return list<Slot>
     */
    private function slots(int $count): array
    {
        return array_map(static fn (int $n): Slot => Slot::participant('s'.$n), range(1, $count));
    }

    /**
     * @param list<BracketMatch> $matches
     *
     * @return list<array{string, string}>
     */
    private function labels(array $matches): array
    {
        return array_map(static fn (BracketMatch $match): array => [$match->first->label(), $match->second->label()], $matches);
    }
}
