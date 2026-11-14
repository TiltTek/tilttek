<?php

declare(strict_types=1);

namespace Tilttek\Quiz;

use Psr\Clock\ClockInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Tilttek\Quiz\Contract\Answer;
use Tilttek\Quiz\Contract\PlayStore;
use Tilttek\Quiz\Contract\QuizCatalog;
use Tilttek\Quiz\Event\PerfectScore;
use Tilttek\Quiz\Event\QuizCompleted;
use Tilttek\Quiz\Exception\QuizConflict;
use Tilttek\Quiz\Exception\QuizRefused;
use Tilttek\Quiz\SelfPaced\Feedback;
use Tilttek\Quiz\SelfPaced\PerfectScoreRule;
use Tilttek\Quiz\SelfPaced\Play;
use Tilttek\Quiz\SelfPaced\Replays;
use Tilttek\Quiz\View\ResultView;

/**
 * Quizzes played by members at their own pace: start a play, answer its questions one by one, and read the result.
 * Each play belongs to its player, who alone answers and reads it.
 *
 * A member's first play of a quiz is the one that counts; a later one is for practice, or refused, by `Replays`. The
 * results are held back until the play is over, unless `Feedback` shows them after each answer. The end of a play
 * dispatches `QuizCompleted`, then `PerfectScore` for a perfect one, by `PerfectScoreRule`: once per quiz and member by
 * default.
 *
 * It keeps no state between calls: the plays live in the store.
 */
final readonly class SelfPacedQuizzes
{
    /** How many times a play is started again when another play of the member took its attempt first. */
    public const int ATTEMPTS = 4;

    public function __construct(
        private QuizCatalog $quizzes,
        private PlayStore $plays,
        private EventDispatcherInterface $events,
        private ClockInterface $clock,
        private Replays $replays = Replays::Practice,
        private Feedback $feedback = Feedback::AfterPlay,
        private PerfectScoreRule $perfectScore = PerfectScoreRule::OncePerQuizAndMember,
    ) {
    }

    /**
     * Starts a play of the quiz for the member: the play that counts when it is their first of the quiz, else a
     * practice play, or a refusal under `Replays::Refused`.
     */
    public function start(string $play, string $quiz, Player $player): Play
    {
        $played = $this->quiz($quiz);

        for ($attempt = 1;; ++$attempt) {
            $before = $this->plays->countOf($played->id, $player->id);

            if ($before > 0 && Replays::Refused === $this->replays) {
                throw QuizRefused::because(\sprintf('The player %s played the quiz %s already.', $player->id, $played->id));
            }

            $started = Play::start($play, $played, $player, $this->clock->now(), $before + 1);

            try {
                $this->plays->add($started);

                return $started;
            } catch (QuizConflict $quizConflict) {
                if ($attempt >= self::ATTEMPTS || $this->plays->find($play) instanceof Play) {
                    throw $quizConflict;
                }
            }
        }
    }

    /**
     * Answers the current question of the player's play, and shows the next one. It returns the result of the answer
     * when the answers are shown: after each one under `Feedback::AfterEachAnswer`, else for the last only, which ends
     * the play; null otherwise.
     */
    public function answer(string $play, string $player, Answer $answer): ?AnswerResult
    {
        $current = $this->play($play, $player);
        $quiz = $this->quiz($current->quiz);
        $result = $current->answer($quiz, $player, $answer, $this->clock->now());
        $this->plays->save($current);

        if (!$current->isFinished()) {
            return Feedback::AfterEachAnswer === $this->feedback ? $result : null;
        }

        $perfect = $current->isPerfect();
        $practice = $current->isPractice();
        $this->events->dispatch(new QuizCompleted($quiz->id, $current->id, $player, $perfect, $practice));

        if ($perfect && (!$practice || PerfectScoreRule::EveryPerfectPlay === $this->perfectScore)) {
            $this->events->dispatch(new PerfectScore($quiz->id, $current->id, $player, $practice));
        }

        return $result;
    }

    /**
     * The player's play; anyone else's is refused as unknown.
     */
    public function play(string $play, string $player): Play
    {
        $found = $this->plays->find($play);

        if (!$found instanceof Play || $found->player->id !== $player) {
            throw QuizRefused::unknownPlay($play);
        }

        return $found;
    }

    /**
     * The results of the player's answers: once the play is over, or as they come under `Feedback::AfterEachAnswer`.
     *
     * @return list<AnswerResult>
     */
    public function results(string $play, string $player): array
    {
        return $this->shown($this->play($play, $player))->results();
    }

    /**
     * What the result template shows of the player's play: once it is over, or as it goes under
     * `Feedback::AfterEachAnswer`.
     */
    public function result(string $play, string $player): ResultView
    {
        $shown = $this->shown($this->play($play, $player));

        return ResultView::ofPlay($shown, $this->quiz($shown->quiz));
    }

    /**
     * Deletes every play of the player, when their account is erased, and returns how many there were. A member who
     * plays again afterwards starts afresh: the plays that told what they had seen are gone.
     */
    public function erasePlayer(string $player): int
    {
        return $this->plays->erasePlayer(Identifier::check($player, 'player'));
    }

    private function shown(Play $play): Play
    {
        if (!$play->isFinished() && Feedback::AfterPlay === $this->feedback) {
            throw QuizRefused::because(\sprintf('The results of the play %s are shown once it is over.', $play->id));
        }

        return $play;
    }

    private function quiz(string $quiz): Quiz
    {
        return $this->quizzes->find($quiz) ?? throw QuizRefused::unknownQuiz($quiz);
    }
}
