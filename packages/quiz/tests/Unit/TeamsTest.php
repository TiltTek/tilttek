<?php

declare(strict_types=1);

namespace Tilttek\Quiz\Tests\Unit;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\Quiz\Answer\ChoiceAnswer;
use Tilttek\Quiz\Answer\NumberAnswer;
use Tilttek\Quiz\AnswerResult;
use Tilttek\Quiz\Exception\InvalidQuiz;
use Tilttek\Quiz\Exception\QuizRefused;
use Tilttek\Quiz\Grade;
use Tilttek\Quiz\Leaderboard;
use Tilttek\Quiz\Live\LiveSession;
use Tilttek\Quiz\Player;
use Tilttek\Quiz\Quiz;
use Tilttek\Quiz\Team\TeamLeaderboard;
use Tilttek\Quiz\Team\TeamScoring;
use Tilttek\Quiz\Team\TeamStanding;
use Tilttek\Quiz\Tests\Unit\Fixture\Quizzes;

#[CoversClass(LiveSession::class)]
#[CoversClass(TeamLeaderboard::class)]
#[CoversClass(TeamStanding::class)]
final class TeamsTest extends TestCase
{
    private Quiz $quiz;

    private DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->quiz = Quizzes::trivia();
        $this->now = new DateTimeImmutable('2026-11-12 20:00:00 UTC');
    }

    public function testAPlayerJoinsTheTeamGivenOrTheSmallest(): void
    {
        $session = LiveSession::open('s1', $this->quiz, '123456', ['red', 'blue', 'green']);

        self::assertSame(['red', 'blue', 'green'], $session->teams());
        self::assertSame(TeamScoring::Total, $session->teamScoring);
        self::assertTrue($session->join(Player::member('m1'), 'blue'));
        self::assertTrue($session->join(Player::member('m2')));
        self::assertTrue($session->join(Player::member('m3')));
        self::assertTrue($session->join(Player::member('m4')));
        self::assertTrue($session->join(Player::member('m5'), 'green'));
        self::assertTrue($session->join(Player::member('m6')));
        self::assertFalse($session->join(Player::member('m1'), 'red'));

        self::assertSame('blue', $session->teamOf('m1'));
        self::assertSame('red', $session->teamOf('m2'));
        self::assertSame('green', $session->teamOf('m3'));
        self::assertSame('red', $session->teamOf('m4'));
        self::assertSame('blue', $session->teamOf('m6'));
        self::assertNull($session->teamOf('nobody'));
        self::assertSame(['m2', 'm4'], $session->members('red'));
        self::assertSame(['m1', 'm6'], $session->members('blue'));
        self::assertSame(['m3', 'm5'], $session->members('green'));
        self::assertSame([], $session->members('pink'));
    }

    public function testTheFirstPlayerWithoutTeamTakesTheFirstEmptyTeam(): void
    {
        $session = LiveSession::open('s1', $this->quiz, '123456', ['red', 'blue', 'green']);

        $session->join(Player::member('m1'));
        $session->join(Player::member('m2'), 'red');
        $session->join(Player::member('m3'));

        self::assertSame('red', $session->teamOf('m1'));
        self::assertSame('blue', $session->teamOf('m3'));
    }

    public function testASessionWithoutTeamsTakesNoTeam(): void
    {
        $session = LiveSession::open('s1', $this->quiz, '123456');

        self::assertSame([], $session->teams());
        self::assertSame([], $session->members('red'));
        self::assertTrue($session->join(Player::member('m1')));
        self::assertNull($session->teamOf('m1'));

        foreach ([
            'The live session s1 is not played by teams.' => static fn (): mixed => $session->join(Player::member('m2'), 'red'),
            'The live session s1 is not played by teams. ' => $session->teamLeaderboard(...),
        ] as $message => $call) {
            try {
                $call();
                self::fail('A team was taken without teams.');
            } catch (QuizRefused $quizRefused) {
                self::assertSame(trim($message), $quizRefused->getMessage());
            }
        }

        self::assertArrayNotHasKey('m2', $session->players());
        self::assertSame(2, $session->version());
    }

    public function testAnUnknownTeamIsRefused(): void
    {
        $session = LiveSession::open('s1', $this->quiz, '123456', ['red', 'blue']);

        $this->expectException(QuizRefused::class);
        $this->expectExceptionMessageIsOrContains('The live session s1 has no team pink.');
        $session->join(Player::member('m1'), 'pink');
    }

    public function testTheTeamsOfASessionAreChecked(): void
    {
        self::assertCount(LiveSession::MOST_TEAMS, LiveSession::open('s', $this->quiz, '123456', array_map(static fn (int $n): string => 't'.$n, range(1, LiveSession::MOST_TEAMS)))->teams());
        self::assertSame(['1', '2'], LiveSession::open('s', $this->quiz, '123456', ['1', '2'])->teams());

        foreach ([
            'A session is played by 2 to 50 teams, or none; 1 given.' => fn (): LiveSession => LiveSession::open('s', $this->quiz, '123456', ['red']),
            'A session is played by 2 to 50 teams, or none; 51 given.' => fn (): LiveSession => LiveSession::open('s', $this->quiz, '123456', array_map(static fn (int $n): string => 't'.$n, range(1, 51))),
            'The teams of a session have distinct identifiers.' => fn (): LiveSession => LiveSession::open('s', $this->quiz, '123456', ['red', 'red']),
            'The identifier of a team is 1 to 128 letters, digits, "_", ".", ":", "@" or "-", starting with a letter or a digit.' => fn (): LiveSession => LiveSession::open('s', $this->quiz, '123456', ['red', 'the blue']),
        ] as $message => $open) {
            try {
                $open();
                self::fail('Teams were accepted: '.$message);
            } catch (InvalidQuiz $invalidQuiz) {
                self::assertSame($message, $invalidQuiz->getMessage());
            }
        }
    }

    public function testTeamsWithNumericIdentifiersAreKeptAsText(): void
    {
        $session = LiveSession::open('s1', $this->quiz, '123456', ['1', '2']);
        $session->join(Player::member('m1'));
        $session->join(Player::member('m2'));

        self::assertSame('1', $session->teamOf('m1'));
        self::assertSame('2', $session->teamOf('m2'));
        self::assertSame(['m1'], $session->members('1'));
        self::assertSame(['m2'], $session->members('2'));
        self::assertSame(['1', '2'], array_map(static fn (TeamStanding $standing): string => $standing->team, $session->teamLeaderboard()->standings));
    }

    public function testTeamsAreRankedByTheTotalOfTheirPlayers(): void
    {
        $session = $this->played(TeamScoring::Total);
        $board = $session->teamLeaderboard();

        self::assertEquals([
            new TeamStanding(1, 'red', 400, 3, 6000, 2),
            new TeamStanding(2, 'blue', 300, 2, 4000, 1),
            new TeamStanding(3, 'green', 0, 0, 0, 0),
        ], $board->standings);
        self::assertEquals([new TeamStanding(1, 'red', 400, 3, 6000, 2)], $board->top(1));
        self::assertEquals(new TeamStanding(2, 'blue', 300, 2, 4000, 1), $board->standingOf('blue'));
        self::assertNull($board->standingOf('pink'));
    }

    public function testTeamsAreRankedByTheMeanOfTheirPlayers(): void
    {
        $board = $this->played(TeamScoring::Average)->teamLeaderboard();

        self::assertEquals([
            new TeamStanding(1, 'blue', 300, 2, 4000, 1),
            new TeamStanding(2, 'red', 200, 3, 3000, 2),
            new TeamStanding(3, 'green', 0, 0, 0, 0),
        ], $board->standings);
    }

    public function testTheMeanIsRoundedHalfUp(): void
    {
        $board = TeamLeaderboard::of(['a', 'b'], [['p1', 'p2'], ['p3', 'p4', 'p5']], $this->board([['p1', 1, 3], ['p2', 2, 4], ['p3', 1, 1], ['p4', 1, 1], ['p5', 0, 0]]), TeamScoring::Average);

        self::assertEquals([
            new TeamStanding(1, 'a', 2, 2, 4, 2),
            new TeamStanding(2, 'b', 1, 2, 1, 3),
        ], $board->standings);
    }

    public function testTeamsEqualOnScoreAndTimeShareARank(): void
    {
        $board = TeamLeaderboard::of(['c', 'a', 'b', 'd'], [['p3'], ['p1'], ['p2'], ['p4']], $this->board([['p1', 10, 5], ['p2', 10, 5], ['p3', 10, 7], ['p4', 20, 9]]), TeamScoring::Total);

        self::assertSame([[1, 'd'], [2, 'a'], [2, 'b'], [4, 'c']], array_map(static fn (TeamStanding $standing): array => [$standing->rank, $standing->team], $board->standings));
        self::assertCount(3, $board->top(2));
        self::assertCount(1, $board->top(1));

        $this->expectException(InvalidQuiz::class);
        $this->expectExceptionMessageIsOrContains('The top of the standings holds at least one place; 0 asked.');
        $board->top(0);
    }

    public function testEveryTeamHasItsPlayers(): void
    {
        self::assertSame([], TeamLeaderboard::of([], [], $this->board([]), TeamScoring::Total)->standings);

        $this->expectException(InvalidQuiz::class);
        $this->expectExceptionMessageIsOrContains('The standings of 2 teams are made from the players of 1.');
        TeamLeaderboard::of(['a', 'b'], [['p1']], $this->board([['p1', 1, 1]]), TeamScoring::Total);
    }

    public function testAGuestsTeamGoesToTheMemberTheyBecame(): void
    {
        $session = LiveSession::open('s1', $this->quiz, '123456', ['red', 'blue']);
        $session->join(Player::guest('g1', 'Pixel'), 'blue');
        $session->join(Player::member('m1'), 'red');
        $session->finish();
        $session->claimGuest('g1', Player::member('m9'));

        self::assertSame('blue', $session->teamOf('m9'));
        self::assertNull($session->teamOf('g1'));
        self::assertSame(['m1'], $session->members('red'));
        self::assertSame(['m9'], $session->members('blue'));
    }

    /**
     * Red: m1 right twice, 3 s each, 300 points; m2 right once at once, 100. Blue: m3 right twice, 2 s each, 300.
     * Green: nobody.
     */
    private function played(TeamScoring $scoring): LiveSession
    {
        $session = LiveSession::open('s1', $this->quiz, '123456', ['red', 'blue', 'green'], $scoring);
        $session->join(Player::member('m1'), 'red');
        $session->join(Player::member('m2'), 'red');
        $session->join(Player::member('m3'), 'blue');

        $session->next($this->quiz, $this->now);
        $session->answer($this->quiz, 'm2', ChoiceAnswer::of('b'), $this->now);
        $session->answer($this->quiz, 'm3', ChoiceAnswer::of('b'), $this->now->modify('+2 seconds'));
        $session->answer($this->quiz, 'm1', ChoiceAnswer::of('b'), $this->now->modify('+3 seconds'));
        $session->close($this->quiz, $this->now->modify('+5 seconds'));

        $shown = $this->now->modify('+10 seconds');
        $session->next($this->quiz, $shown);
        $session->answer($this->quiz, 'm1', new NumberAnswer(1974), $shown->modify('+3 seconds'));
        $session->answer($this->quiz, 'm3', new NumberAnswer(1975), $shown->modify('+2 seconds'));

        return $session;
    }

    /**
     * @param list<array{string, int, int}> $rows player, score, milliseconds
     */
    private function board(array $rows): Leaderboard
    {
        $results = [];

        foreach ($rows as [$player, $score, $elapsed]) {
            $results[$player] = new AnswerResult('q', $score > 0 ? Grade::right() : Grade::wrong(), $score, 0, $elapsed);
        }

        return Leaderboard::of(array_keys($results), [$results]);
    }
}
