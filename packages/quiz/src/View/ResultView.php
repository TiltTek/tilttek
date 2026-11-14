<?php

declare(strict_types=1);

namespace Tilttek\Quiz\View;

use Tilttek\Quiz\Exception\QuizRefused;
use Tilttek\Quiz\Live\LiveSession;
use Tilttek\Quiz\Live\LiveState;
use Tilttek\Quiz\Quiz;
use Tilttek\Quiz\SelfPaced\Play;
use Tilttek\Quiz\Team\TeamStanding;

/**
 * What `templates/result.html.twig` shows of a player's run: the score out of the most the quiz gives, the rank of a
 * live run, the team and its rank in a session played by teams, whether the run is over and perfect and the team won,
 * which the Stimulus controller of the package says to the page, and whether a play at one's own pace is a practice
 * play, which does not count.
 *
 * `ofPlay()` shows a play as it stands; a page shows a member's play through `SelfPacedQuizzes::result()`, which holds
 * a play that is not over back.
 */
final readonly class ResultView
{
    public function __construct(
        public string $quiz,
        public string $run,
        public string $player,
        public int $score,
        public int $maxScore,
        public bool $completed,
        public bool $perfect,
        public ?int $rank = null,
        public ?string $team = null,
        public ?int $teamRank = null,
        public bool $teamWon = false,
        public bool $practice = false,
    ) {
    }

    public static function ofPlay(Play $play, Quiz $quiz): self
    {
        return new self($quiz->id, $play->id, $play->player->id, $play->score(), $quiz->maxScore(), $play->isFinished(), $play->isPerfect(), practice: $play->isPractice());
    }

    public static function ofLive(LiveSession $session, Quiz $quiz, string $player): self
    {
        $standing = $session->leaderboard()->standingOf($player) ?? throw QuizRefused::because(\sprintf('The player %s has not joined the live session %s.', $player, $session->id));

        $completed = LiveState::Finished === $session->state();
        $team = $session->teamOf($player);
        $teamStanding = null === $team ? null : $session->teamLeaderboard()->standingOf($team);

        if (!$teamStanding instanceof TeamStanding) {
            return new self($quiz->id, $session->id, $player, $standing->score, $quiz->maxScore(), $completed, $session->isPerfect($quiz, $player), $standing->rank);
        }

        return new self(
            $quiz->id,
            $session->id,
            $player,
            $standing->score,
            $quiz->maxScore(),
            $completed,
            $session->isPerfect($quiz, $player),
            $standing->rank,
            $team,
            $teamStanding->rank,
            $completed && 1 === $teamStanding->rank && $teamStanding->score > 0,
        );
    }
}
