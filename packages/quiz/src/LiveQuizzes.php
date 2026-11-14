<?php

declare(strict_types=1);

namespace Tilttek\Quiz;

use Closure;
use Psr\Clock\ClockInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Random\Randomizer;
use Tilttek\Quiz\Contract\Answer;
use Tilttek\Quiz\Contract\LiveSessionStore;
use Tilttek\Quiz\Contract\PseudonymFilter;
use Tilttek\Quiz\Contract\QuizCatalog;
use Tilttek\Quiz\Event\GuestInvited;
use Tilttek\Quiz\Event\LiveQuizFinished;
use Tilttek\Quiz\Event\PerfectScore;
use Tilttek\Quiz\Event\PlayerJoined;
use Tilttek\Quiz\Event\QuestionClosed;
use Tilttek\Quiz\Event\QuestionShown;
use Tilttek\Quiz\Event\QuizCompleted;
use Tilttek\Quiz\Event\TeamWon;
use Tilttek\Quiz\Exception\QuizConflict;
use Tilttek\Quiz\Exception\QuizRefused;
use Tilttek\Quiz\Live\LiveSession;
use Tilttek\Quiz\Live\LiveState;
use Tilttek\Quiz\Team\TeamScoring;

/**
 * Quizzes played live: the host opens a session, players join with its PIN, and the host shows, closes and moves
 * through the questions. A change that meets a newer version in the store is read again and made again, a few times,
 * so that the answers of many players at once are all kept.
 *
 * A new player dispatches `PlayerJoined`; a question shown, `QuestionShown`; a question closed, `QuestionClosed`. The
 * end of a session dispatches `LiveQuizFinished`, then `QuizCompleted` and maybe `PerfectScore` for each player,
 * `TeamWon` for each winning team, and `GuestInvited` for each guest. Each event follows the saving of the change it
 * tells. It keeps no state between calls: the sessions live in the store.
 */
final readonly class LiveQuizzes
{
    /** How many times a change is made again when the session changed in between. */
    public const int ATTEMPTS = 8;

    /** How many PINs are drawn before giving up: a million PINs leave room for many sessions at once. */
    public const int PIN_DRAWS = 16;

    public function __construct(
        private QuizCatalog $quizzes,
        private LiveSessionStore $sessions,
        private EventDispatcherInterface $events,
        private ClockInterface $clock,
        private ?PseudonymFilter $pseudonyms = null,
        private Randomizer $randomizer = new Randomizer(),
    ) {
    }

    /**
     * Opens a session of the quiz, with a PIN of six digits no other session that is not over holds, played by each
     * player for themselves, or by teams.
     *
     * @param list<string> $teams
     */
    public function open(string $session, string $quiz, array $teams = [], TeamScoring $teamScoring = TeamScoring::Total): LiveSession
    {
        $played = $this->quiz($quiz);

        for ($draw = 0; $draw < self::PIN_DRAWS; ++$draw) {
            $pin = \sprintf('%06d', $this->randomizer->getInt(0, 999_999));

            if (!$this->sessions->findByPin($pin) instanceof LiveSession) {
                $opened = LiveSession::open($session, $played, $pin, $teams, $teamScoring);
                $this->sessions->add($opened);

                return $opened;
            }
        }

        throw QuizRefused::because('No free PIN was found for a new live session; try again.');
    }

    /**
     * Lets a player in by the PIN of a session that is not over, as the player typed it; a guest's pseudonym must pass
     * the filter. In a session played by teams, the player joins the team given, or the smallest.
     */
    public function join(string $pin, Player $player, ?string $team = null): LiveSession
    {
        $pin = Pin::parse($pin);

        if (null !== $player->pseudonym && $this->pseudonyms instanceof PseudonymFilter && !$this->pseudonyms->allows($player->pseudonym)) {
            throw QuizRefused::because('This pseudonym is not allowed; choose another.');
        }

        [$joined, $new] = $this->change(
            fn (): LiveSession => $this->sessions->findByPin($pin) ?? throw QuizRefused::because('No live session that is not over has this PIN.'),
            static fn (LiveSession $session): bool => $session->join($player, $team),
        );

        if ($new) {
            $this->events->dispatch(new PlayerJoined($joined->id, $player->id));
        }

        return $joined;
    }

    public function next(string $session): QuestionTimer
    {
        [$shown, $timer] = $this->change(
            fn (): LiveSession => $this->session($session),
            fn (LiveSession $live): QuestionTimer => $live->next($this->quiz($live->quiz), $this->clock->now()),
        );
        $this->events->dispatch(new QuestionShown($shown->quiz, $shown->id, $shown->position()));

        return $timer;
    }

    public function answer(string $session, string $player, Answer $answer): AnswerResult
    {
        return $this->change(
            fn (): LiveSession => $this->session($session),
            fn (LiveSession $live): AnswerResult => $live->answer($this->quiz($live->quiz), $player, $answer, $this->clock->now()),
        )[1];
    }

    /**
     * Closes the question shown; a question closed already stays closed, and nothing is said again.
     */
    public function close(string $session): LiveSession
    {
        [$closed, $changed] = $this->change(
            fn (): LiveSession => $this->session($session),
            fn (LiveSession $live): bool => $this->closeOne($live),
        );

        if ($changed) {
            $this->events->dispatch(new QuestionClosed($closed->quiz, $closed->id, $closed->position()));
        }

        return $closed;
    }

    public function finish(string $session): LiveSession
    {
        $finished = $this->change(
            fn (): LiveSession => $this->session($session),
            static function (LiveSession $live): null {
                $live->finish();

                return null;
            },
        )[0];
        $quiz = $this->quiz($finished->quiz);
        $this->events->dispatch(new LiveQuizFinished($quiz->id, $finished->id));

        foreach ($finished->players() as $player) {
            $perfect = $finished->isPerfect($quiz, $player->id);
            $this->events->dispatch(new QuizCompleted($quiz->id, $finished->id, $player->id, $perfect));

            if ($perfect) {
                $this->events->dispatch(new PerfectScore($quiz->id, $finished->id, $player->id));
            }
        }

        if ([] !== $finished->teams()) {
            foreach ($finished->teamLeaderboard()->top(1) as $winner) {
                if ($winner->score > 0) {
                    $this->events->dispatch(new TeamWon($quiz->id, $finished->id, $winner->team));
                }
            }
        }

        foreach ($finished->guests() as $guest) {
            $this->events->dispatch(new GuestInvited($finished->id, $guest->id));
        }

        return $finished;
    }

    /**
     * Gives the place and the score of a guest to the member they became, once the session is over.
     */
    public function claimGuest(string $session, string $guest, Player $member): LiveSession
    {
        return $this->change(
            fn (): LiveSession => $this->session($session),
            static function (LiveSession $live) use ($guest, $member): null {
                $live->claimGuest($guest, $member);

                return null;
            },
        )[0];
    }

    public function session(string $session): LiveSession
    {
        return $this->sessions->find($session) ?? throw QuizRefused::unknownSession($session);
    }

    private function closeOne(LiveSession $live): bool
    {
        if (LiveState::Question !== $live->state()) {
            return false;
        }

        $live->close($this->quiz($live->quiz), $this->clock->now());

        return true;
    }

    /**
     * Reads the session, changes it, and saves it when its version moved; reads it again on a conflict.
     *
     * @template T
     *
     * @param Closure(): LiveSession  $read
     * @param Closure(LiveSession): T $change
     *
     * @return array{LiveSession, T}
     */
    private function change(Closure $read, Closure $change): array
    {
        for ($attempt = 1;; ++$attempt) {
            $session = $read();
            $version = $session->version();
            $outcome = $change($session);

            if ($session->version() === $version) {
                return [$session, $outcome];
            }

            try {
                $this->sessions->save($session);

                return [$session, $outcome];
            } catch (QuizConflict $conflict) {
                if ($attempt >= self::ATTEMPTS) {
                    throw $conflict;
                }
            }
        }
    }

    private function quiz(string $quiz): Quiz
    {
        return $this->quizzes->find($quiz) ?? throw QuizRefused::unknownQuiz($quiz);
    }
}
