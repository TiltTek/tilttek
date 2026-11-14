<?php

declare(strict_types=1);

namespace Tilttek\Quiz;

use Closure;
use Psr\Clock\ClockInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Random\Randomizer;
use Throwable;
use Tilttek\Quiz\Contract\Answer;
use Tilttek\Quiz\Contract\LiveSessionStore;
use Tilttek\Quiz\Contract\PseudonymFilter;
use Tilttek\Quiz\Contract\QuizCatalog;
use Tilttek\Quiz\Event\PlayerJoined;
use Tilttek\Quiz\Event\QuestionClosed;
use Tilttek\Quiz\Event\QuestionShown;
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
 *
 * The events of the end are written with the session that is over, and each stays there until it was dispatched
 * without an exception: a listener that throws leaves it there, the other events are dispatched all the same, and the
 * first exception is thrown again at the end. `finish()` called again, or `deliver()`, dispatches what is left. An
 * event may thus be dispatched more than once: the listeners are idempotent, keyed by the event's `key()`.
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
     * player for themselves, or by teams. The store takes the PIN only while it is free, in one step: a PIN another
     * session took in between is drawn again, up to `PIN_DRAWS` times.
     *
     * @param list<string> $teams
     */
    public function open(string $session, string $quiz, array $teams = [], TeamScoring $teamScoring = TeamScoring::Total): LiveSession
    {
        $played = $this->quiz($quiz);

        for ($draw = 0; $draw < self::PIN_DRAWS; ++$draw) {
            $opened = LiveSession::open($session, $played, \sprintf('%06d', $this->randomizer->getInt(0, 999_999)), $teams, $teamScoring);

            if ($this->sessions->add($opened)) {
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

    /**
     * Ends the session, then dispatches the events of its end not delivered yet. A session over whose events are not
     * all delivered is not ended again: its events left are dispatched; one whose events are all delivered is refused.
     */
    public function finish(string $session): LiveSession
    {
        $finished = $this->change(
            fn (): LiveSession => $this->session($session),
            function (LiveSession $live): null {
                if (LiveState::Finished !== $live->state() || [] === $live->undelivered()) {
                    $live->finish($this->quiz($live->quiz));
                }

                return null;
            },
        )[0];
        [$delivered, , $failure] = $this->deliverEnd($finished);
        $this->rethrow($failure);

        return $delivered;
    }

    /**
     * Dispatches again the events of the end of the session not delivered yet, to resume after a listener failed.
     *
     * @return int how many were delivered
     */
    public function deliver(string $session): int
    {
        [, $count, $failure] = $this->deliverEnd($this->session($session));
        $this->rethrow($failure);

        return $count;
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

    /**
     * Dispatches the events of the end not delivered yet, and forgets those dispatched without an exception.
     *
     * @return array{LiveSession, int, ?Throwable} the session as kept, how many were delivered, and the first exception
     *                                             thrown
     */
    private function deliverEnd(LiveSession $session): array
    {
        $keys = [];
        $failure = null;

        foreach ($session->undelivered() as $event) {
            try {
                $this->events->dispatch($event);
            } catch (Throwable $thrown) {
                $failure ??= $thrown;

                continue;
            }

            $keys[] = $event->key();
        }

        if ([] !== $keys) {
            $session = $this->change(
                fn (): LiveSession => $this->session($session->id),
                static function (LiveSession $live) use ($keys): null {
                    $live->markDelivered(...$keys);

                    return null;
                },
            )[0];
        }

        return [$session, \count($keys), $failure];
    }

    private function rethrow(?Throwable $failure): void
    {
        if ($failure instanceof Throwable) {
            throw $failure;
        }
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
