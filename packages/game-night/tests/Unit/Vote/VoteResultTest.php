<?php

declare(strict_types=1);

namespace Tilttek\GameNight\Tests\Unit\Vote;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\GameNight\Vote\Tally;
use Tilttek\GameNight\Vote\VoteOutcome;
use Tilttek\GameNight\Vote\VoteResult;

#[CoversClass(VoteResult::class)]
#[CoversClass(Tally::class)]
final class VoteResultTest extends TestCase
{
    public function testAVoteWithoutBallotsHasNoWinner(): void
    {
        $result = VoteResult::count(['g-1', 'g-2'], []);

        self::assertSame(0, $result->votes);
        self::assertSame(VoteOutcome::NoVotes, $result->outcome);
        self::assertSame([], $result->leaders);
        self::assertNull($result->winner());
        self::assertEquals([new Tally('g-1', 0, 0), new Tally('g-2', 0, 0)], $result->tallies);
    }

    public function testAVoteWithoutCandidatesHasNoWinner(): void
    {
        $result = VoteResult::count([], []);

        self::assertSame(VoteOutcome::NoVotes, $result->outcome);
        self::assertSame([], $result->tallies);
    }

    public function testTheGameWithTheMostVotesWins(): void
    {
        $result = VoteResult::count(['g-1', 'g-2', 'g-3'], ['m-1' => 'g-2', 'm-2' => 'g-3', 'm-3' => 'g-2']);

        self::assertSame(3, $result->votes);
        self::assertSame(VoteOutcome::Winner, $result->outcome);
        self::assertSame('g-2', $result->winner());
        self::assertSame(['g-2'], $result->leaders);
        self::assertEquals([new Tally('g-2', 2, 67), new Tally('g-3', 1, 33), new Tally('g-1', 0, 0)], $result->tallies);
    }

    public function testATieIsShownAsATie(): void
    {
        $result = VoteResult::count(['g-1', 'g-2', 'g-3'], ['m-1' => 'g-3', 'm-2' => 'g-1']);

        self::assertSame(VoteOutcome::Tie, $result->outcome);
        self::assertNull($result->winner());
        self::assertSame(['g-1', 'g-3'], $result->leaders);
        self::assertEquals([new Tally('g-1', 1, 50), new Tally('g-3', 1, 50), new Tally('g-2', 0, 0)], $result->tallies);
    }

    public function testTheSharesAreRoundedHalfUp(): void
    {
        $ballots = [];

        foreach (range(1, 8) as $i) {
            $ballots['m-'.$i] = 1 === $i ? 'g-1' : 'g-2';
        }

        $result = VoteResult::count(['g-1', 'g-2'], $ballots);

        self::assertSame(13, $result->tallies[1]->percent);
        self::assertSame(88, $result->tallies[0]->percent);
        self::assertSame(['g-2', 'g-1'], [$result->tallies[0]->game, $result->tallies[1]->game]);
        self::assertSame(7, $result->tallies[0]->votes);
    }

    public function testABallotForAGameNotProposedIsCountedAsAVoterOnly(): void
    {
        $result = VoteResult::count(['g-1'], ['m-1' => 'g-1', 'm-2' => 'g-9']);

        self::assertSame(2, $result->votes);
        self::assertEquals([new Tally('g-1', 1, 50)], $result->tallies);
    }
}
