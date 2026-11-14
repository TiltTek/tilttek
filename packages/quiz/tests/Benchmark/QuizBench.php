<?php

declare(strict_types=1);

namespace Tilttek\Quiz\Tests\Benchmark;

use DateTimeImmutable;
use Tilttek\Quiz\Answer\ChoiceAnswer;
use Tilttek\Quiz\Live\LiveSession;
use Tilttek\Quiz\Player;
use Tilttek\Quiz\Pseudonym\WordListFilter;
use Tilttek\Quiz\Question\Choice;
use Tilttek\Quiz\Question\SingleChoiceQuestion;
use Tilttek\Quiz\Quiz;
use Tilttek\Quiz\Scoring;

/**
 * What a live quiz costs: one answer scored in a session of 1 000 players, the standings of that session after ten
 * questions — what the host's screen reads after each question —, the standings of its ten teams, and a pseudonym
 * checked against the library's word list, as each guest joins.
 */
final class QuizBench
{
    private readonly Quiz $quiz;

    private readonly LiveSession $session;

    private readonly LiveSession $answering;

    private readonly DateTimeImmutable $now;

    private readonly LiveSession $teams;

    private readonly WordListFilter $filter;

    private int $player = 0;

    public function __construct()
    {
        $choices = [new Choice('a', 'A'), new Choice('b', 'B'), new Choice('c', 'C'), new Choice('d', 'D')];
        $this->quiz = new Quiz('bench', array_map(static fn (int $n): SingleChoiceQuestion => new SingleChoiceQuestion('q'.$n, 'Question '.$n, $choices, 'b'), range(1, 10)), Scoring::speed(), 20);
        $this->now = new DateTimeImmutable('2026-11-12 20:00:00 UTC');
        $this->session = LiveSession::open('standings', $this->quiz, '123456');

        for ($player = 1; $player <= LiveSession::MOST_PLAYERS; ++$player) {
            $this->session->join(Player::member('member:'.$player));
        }

        for ($question = 0; $question < 10; ++$question) {
            $this->session->next($this->quiz, $this->now);

            for ($player = 1; $player <= LiveSession::MOST_PLAYERS; $player += 2) {
                $this->session->answer($this->quiz, 'member:'.$player, ChoiceAnswer::of(0 === $player % 3 ? 'a' : 'b'), $this->now->modify(\sprintf('+%d milliseconds', $player)));
            }

            $this->session->close($this->quiz, $this->now);
        }

        $this->answering = LiveSession::open('answers', $this->quiz, '654321');

        for ($player = 1; $player <= LiveSession::MOST_PLAYERS; ++$player) {
            $this->answering->join(Player::member('member:'.$player));
        }

        $this->answering->next($this->quiz, $this->now);

        $this->teams = LiveSession::open('teams', $this->quiz, '123456', array_map(static fn (int $n): string => 'team-'.$n, range(1, 10)));

        for ($player = 1; $player <= LiveSession::MOST_PLAYERS; ++$player) {
            $this->teams->join(Player::member('member:'.$player));
        }

        $this->teams->next($this->quiz, $this->now);

        for ($player = 1; $player <= LiveSession::MOST_PLAYERS; ++$player) {
            $this->teams->answer($this->quiz, 'member:'.$player, ChoiceAnswer::of(0 === $player % 3 ? 'a' : 'b'), $this->now);
        }

        $this->filter = WordListFilter::defaults();
    }

    /**
     * @Revs(500)
     *
     * @Iterations(3)
     */
    public function benchAnswerInASessionOf1000(): void
    {
        $this->answering->answer($this->quiz, 'member:'.(++$this->player % LiveSession::MOST_PLAYERS + 1), ChoiceAnswer::of('b'), $this->now);
    }

    /**
     * @Revs(10)
     *
     * @Iterations(3)
     */
    public function benchStandingsOf1000After10Questions(): void
    {
        $this->session->leaderboard();
    }

    /**
     * @Revs(10)
     *
     * @Iterations(3)
     */
    public function benchTeamStandingsOf1000In10Teams(): void
    {
        $this->teams->teamLeaderboard();
    }

    /**
     * @Revs(1000)
     *
     * @Iterations(3)
     */
    public function benchFilterAPseudonym(): void
    {
        $this->filter->allows('Pixel Wanderer 42');
    }
}
