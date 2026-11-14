<?php

declare(strict_types=1);

namespace Tilttek\Quiz\Live;

use DateTimeImmutable;
use Tilttek\Quiz\AnswerResult;
use Tilttek\Quiz\Contract\Answer;
use Tilttek\Quiz\Event\GuestInvited;
use Tilttek\Quiz\Event\LiveQuizFinished;
use Tilttek\Quiz\Event\PerfectScore;
use Tilttek\Quiz\Event\QuizCompleted;
use Tilttek\Quiz\Event\TeamWon;
use Tilttek\Quiz\Exception\InvalidQuiz;
use Tilttek\Quiz\Exception\QuizRefused;
use Tilttek\Quiz\Identifier;
use Tilttek\Quiz\Leaderboard;
use Tilttek\Quiz\Player;
use Tilttek\Quiz\QuestionTimer;
use Tilttek\Quiz\Quiz;
use Tilttek\Quiz\Team\TeamLeaderboard;
use Tilttek\Quiz\Team\TeamScoring;

/**
 * A quiz played live: the host shows each question to every player at once, closes it, shows the standings, and
 * moves on. Players join with the session's PIN, members or guests under a pseudonym; a pseudonym is taken once per
 * session, whatever its case. A session may be played by teams: each player joins one, and the teams are ranked by
 * the scores of their players.
 *
 * Each change gives the session its next version, which the store checks. The events of its end are written with it,
 * in the same version, and each leaves the session once it was dispatched without an exception.
 */
final class LiveSession
{
    /** The most players a session takes. */
    public const int MOST_PLAYERS = 1000;

    /** The most teams a session is played by. */
    public const int MOST_TEAMS = 50;

    private LiveState $state = LiveState::Lobby;

    /** @var array<string, Player> by identifier */
    private array $players = [];

    /** @var array<string, string> the identifier of each guest, by folded pseudonym */
    private array $pseudonyms = [];

    /** @var array<string, string> the team of each player, by identifier */
    private array $teamOf = [];

    private int $position = -1;

    /**
     * @var array<string, LiveQuizFinished|QuizCompleted|PerfectScore|TeamWon|GuestInvited> the events of the end not
     *                                                                                      delivered yet, by key, in order
     */
    private array $undelivered = [];

    private ?DateTimeImmutable $shownAt = null;

    /** @var list<array<string, AnswerResult>> the results of each question, by position then player */
    private array $results = [];

    /**
     * @param list<string> $teams
     */
    private function __construct(
        public readonly string $id,
        public readonly string $quiz,
        public readonly string $pin,
        private readonly array $teams,
        public readonly TeamScoring $teamScoring,
        private int $version = 1,
    ) {
    }

    /**
     * Opens a session, played by each player for themselves, or by 2 to `MOST_TEAMS` teams with distinct identifiers.
     *
     * @param list<string> $teams
     */
    public static function open(string $id, Quiz $quiz, string $pin, array $teams = [], TeamScoring $teamScoring = TeamScoring::Total): self
    {
        Identifier::check($id, 'live session');

        if (1 !== preg_match('/^\d{6}$/D', $pin)) {
            throw InvalidQuiz::because('The PIN of a live session is six digits.');
        }

        if ([] !== $teams && (\count($teams) < 2 || \count($teams) > self::MOST_TEAMS)) {
            throw InvalidQuiz::because(\sprintf('A session is played by 2 to %d teams, or none; %d given.', self::MOST_TEAMS, \count($teams)));
        }

        foreach ($teams as $team) {
            Identifier::check($team, 'team');
        }

        if (\count(array_unique($teams)) !== \count($teams)) {
            throw InvalidQuiz::because('The teams of a session have distinct identifiers.');
        }

        return new self($id, $quiz->id, $pin, $teams, $teamScoring);
    }

    /**
     * Lets a player in, at any moment until the session is over: a player who joins late misses the questions
     * already closed. A player who has joined already is let in again, in the same team. In a session played by
     * teams, a player joins the team given, or, without one, the team with the fewest players, the first declared on
     * a tie.
     *
     * @return bool whether the player is new to the session
     */
    public function join(Player $player, ?string $team = null): bool
    {
        if (LiveState::Finished === $this->state) {
            throw QuizRefused::because(\sprintf('The live session %s is over.', $this->id));
        }

        if (isset($this->players[$player->id])) {
            return false;
        }

        if (\count($this->players) >= self::MOST_PLAYERS) {
            throw QuizRefused::because(\sprintf('The live session %s holds %d players, the most it takes.', $this->id, self::MOST_PLAYERS));
        }

        $team = $this->teamFor($team);

        if (null !== $player->pseudonym) {
            $folded = mb_strtolower($player->pseudonym, 'UTF-8');

            if (isset($this->pseudonyms[$folded])) {
                throw QuizRefused::because('Another guest of the session plays under this pseudonym.');
            }

            $this->pseudonyms[$folded] = $player->id;
        }

        $this->players[$player->id] = $player;

        if (null !== $team) {
            $this->teamOf[$player->id] = $team;
        }

        ++$this->version;

        return true;
    }

    /**
     * Shows the next question to every player, from the lobby or the review of the previous one.
     */
    public function next(Quiz $quiz, DateTimeImmutable $now): QuestionTimer
    {
        $this->checkQuiz($quiz);

        if (LiveState::Lobby !== $this->state && LiveState::Review !== $this->state) {
            throw QuizRefused::because(\sprintf('The live session %s shows its next question from the lobby or a review only.', $this->id));
        }

        if ($this->position + 1 >= $quiz->count()) {
            throw QuizRefused::because(\sprintf('The live session %s has asked every question of its quiz.', $this->id));
        }

        ++$this->position;
        $this->shownAt = $now;
        $this->results[] = [];
        $this->state = LiveState::Question;
        ++$this->version;

        return $this->shown($quiz);
    }

    /**
     * Records a player's answer to the question shown. Each player answers once; once the time is up, the answer is
     * refused, and the closing of the question records the player as timed out.
     */
    public function answer(Quiz $quiz, string $player, Answer $answer, DateTimeImmutable $now): AnswerResult
    {
        $this->checkQuiz($quiz);

        if (LiveState::Question !== $this->state) {
            throw QuizRefused::because(\sprintf('The live session %s shows no question to answer.', $this->id));
        }

        if (!isset($this->players[$player])) {
            throw QuizRefused::because(\sprintf('The player %s has not joined the live session %s.', $player, $this->id));
        }

        if (isset($this->results[$this->position][$player])) {
            throw QuizRefused::because(\sprintf('The player %s has answered this question already.', $player));
        }

        $timer = $this->shown($quiz);

        if ($timer->isOver($now)) {
            throw QuizRefused::because('The time of this question is up.');
        }

        $result = $quiz->score($this->position, $answer, $timer, $now);
        $this->results[$this->position][$player] = $result;
        ++$this->version;

        return $result;
    }

    /**
     * Closes the question shown: no answer is taken any more, and every player who gave none is timed out.
     */
    public function close(Quiz $quiz, DateTimeImmutable $now): void
    {
        $this->checkQuiz($quiz);

        if (LiveState::Question !== $this->state) {
            throw QuizRefused::because(\sprintf('The live session %s shows no question to close.', $this->id));
        }

        $question = $quiz->question($this->position)->id();
        $elapsed = $this->shown($quiz)->elapsed($now);

        foreach ($this->players as $player) {
            $this->results[$this->position][$player->id] ??= AnswerResult::timedOut($question, $elapsed);
        }

        $this->state = LiveState::Review;
        ++$this->version;
    }

    /**
     * Ends the session, from the lobby or a review: its standings are final. A question shown is closed first. Given its
     * quiz, as `LiveQuizzes` gives it, the session keeps the events of its end until they are delivered:
     * `LiveQuizFinished`, then for each player `QuizCompleted` and maybe `PerfectScore`, `TeamWon` for each winning
     * team, and `GuestInvited` for each guest.
     */
    public function finish(?Quiz $quiz = null): void
    {
        if ($quiz instanceof Quiz) {
            $this->checkQuiz($quiz);
        }

        if (LiveState::Question === $this->state) {
            throw QuizRefused::because(\sprintf('The live session %s shows a question: close it before the end.', $this->id));
        }

        if (LiveState::Finished === $this->state) {
            throw QuizRefused::because(\sprintf('The live session %s is over already.', $this->id));
        }

        $this->state = LiveState::Finished;
        ++$this->version;

        if ($quiz instanceof Quiz) {
            foreach ($this->endEvents($quiz) as $event) {
                $this->undelivered[$event->key()] = $event;
            }
        }
    }

    /**
     * The events of the end not delivered yet, in the order they are dispatched.
     *
     * @return list<LiveQuizFinished|QuizCompleted|PerfectScore|TeamWon|GuestInvited>
     */
    public function undelivered(): array
    {
        return array_values($this->undelivered);
    }

    /**
     * Forgets the events of these keys, dispatched without an exception, in one change: the session takes its next
     * version; nothing when it holds none of them undelivered.
     */
    public function markDelivered(string ...$keys): void
    {
        $left = array_diff_key($this->undelivered, array_flip($keys));

        if (\count($left) !== \count($this->undelivered)) {
            $this->undelivered = $left;
            ++$this->version;
        }
    }

    /**
     * Gives a guest's place, and their score, to the member they became once the session is over.
     */
    public function claimGuest(string $guest, Player $member): void
    {
        if (LiveState::Finished !== $this->state) {
            throw QuizRefused::because(\sprintf('The live session %s is not over: a guest becomes a member once it is.', $this->id));
        }

        $player = $this->players[$guest] ?? null;

        if (null === $player || null === $player->pseudonym) {
            throw QuizRefused::because(\sprintf('The live session %s has no guest %s.', $this->id, $guest));
        }

        if ($member->isGuest()) {
            throw QuizRefused::because("A guest's place goes to a member, never to another guest.");
        }

        if (isset($this->players[$member->id])) {
            throw QuizRefused::because(\sprintf('The member %s played the live session %s already.', $member->id, $this->id));
        }

        unset($this->players[$guest], $this->pseudonyms[mb_strtolower($player->pseudonym, 'UTF-8')]);
        $this->players[$member->id] = $member;

        if (isset($this->teamOf[$guest])) {
            $this->teamOf[$member->id] = $this->teamOf[$guest];
            unset($this->teamOf[$guest]);
        }

        foreach ($this->results as $position => $results) {
            if (isset($results[$guest])) {
                $this->results[$position][$member->id] = $results[$guest];
                unset($this->results[$position][$guest]);
            }
        }

        ++$this->version;
    }

    public function state(): LiveState
    {
        return $this->state;
    }

    /** The position of the question shown or last shown, or -1 before the first. */
    public function position(): int
    {
        return $this->position;
    }

    /**
     * The timer of the question shown or last shown, or null before the first.
     */
    public function timer(Quiz $quiz): ?QuestionTimer
    {
        return $this->shownAt instanceof DateTimeImmutable ? $quiz->timer($this->position, $this->shownAt) : null;
    }

    /**
     * @return array<string, Player> by identifier
     */
    public function players(): array
    {
        return $this->players;
    }

    /**
     * @return list<Player>
     */
    public function guests(): array
    {
        return array_values(array_filter($this->players, static fn (Player $player): bool => $player->isGuest()));
    }

    /**
     * The results of a player, by position of question.
     *
     * @return array<int, AnswerResult>
     */
    public function resultsOf(string $player): array
    {
        $results = [];

        foreach ($this->results as $position => $byPlayer) {
            if (isset($byPlayer[$player])) {
                $results[$position] = $byPlayer[$player];
            }
        }

        return $results;
    }

    /**
     * Whether the player answered every question of the quiz fully right, once the session is over.
     */
    public function isPerfect(Quiz $quiz, string $player): bool
    {
        if (LiveState::Finished !== $this->state) {
            return false;
        }

        $results = $this->resultsOf($player);

        if (\count($results) !== $quiz->count()) {
            return false;
        }

        return array_all($results, static fn (AnswerResult $result): bool => $result->grade->isRight());
    }

    public function leaderboard(): Leaderboard
    {
        $players = [];

        foreach ($this->players as $player) {
            $players[] = $player->id;
        }

        return Leaderboard::of($players, $this->results);
    }

    /**
     * The teams of a session played by teams, in the order declared; none for a session played by each player.
     *
     * @return list<string>
     */
    public function teams(): array
    {
        return $this->teams;
    }

    /** The team of a player, or null in a session played by each player. */
    public function teamOf(string $player): ?string
    {
        return $this->teamOf[$player] ?? null;
    }

    /**
     * The players of a team, in the order they joined.
     *
     * @return list<string>
     */
    public function members(string $team): array
    {
        $members = [];

        foreach ($this->players as $player) {
            if (($this->teamOf[$player->id] ?? null) === $team) {
                $members[] = $player->id;
            }
        }

        return $members;
    }

    /**
     * The standings of the teams, by the session's `TeamScoring`.
     */
    public function teamLeaderboard(): TeamLeaderboard
    {
        if ([] === $this->teams) {
            throw QuizRefused::because(\sprintf('The live session %s is not played by teams.', $this->id));
        }

        return TeamLeaderboard::of($this->teams, array_map($this->members(...), $this->teams), $this->leaderboard(), $this->teamScoring);
    }

    public function version(): int
    {
        return $this->version;
    }

    /**
     * @return list<LiveQuizFinished|QuizCompleted|PerfectScore|TeamWon|GuestInvited>
     */
    private function endEvents(Quiz $quiz): array
    {
        $events = [new LiveQuizFinished($quiz->id, $this->id)];

        foreach ($this->players as $player) {
            $perfect = $this->isPerfect($quiz, $player->id);
            $events[] = new QuizCompleted($quiz->id, $this->id, $player->id, $perfect);

            if ($perfect) {
                $events[] = new PerfectScore($quiz->id, $this->id, $player->id);
            }
        }

        if ([] !== $this->teams) {
            foreach ($this->teamLeaderboard()->top(1) as $winner) {
                if ($winner->score > 0) {
                    $events[] = new TeamWon($quiz->id, $this->id, $winner->team);
                }
            }
        }

        foreach ($this->guests() as $guest) {
            $events[] = new GuestInvited($this->id, $guest->id);
        }

        return $events;
    }

    /**
     * The team a new player joins: the one given, or the one with the fewest players; none without teams.
     */
    private function teamFor(?string $team): ?string
    {
        if ([] === $this->teams) {
            if (null !== $team) {
                throw QuizRefused::because(\sprintf('The live session %s is not played by teams.', $this->id));
            }

            return null;
        }

        if (null !== $team) {
            if (!\in_array($team, $this->teams, true)) {
                throw QuizRefused::because(\sprintf('The live session %s has no team %s.', $this->id, $team));
            }

            return $team;
        }

        $sizes = array_map(fn (string $candidate): int => \count(array_keys($this->teamOf, $candidate, true)), $this->teams);

        return $this->teams[array_keys($sizes, min($sizes), true)[0]];
    }

    private function shown(Quiz $quiz): QuestionTimer
    {
        return $quiz->timer($this->position, $this->shownAt ?? throw QuizRefused::because(\sprintf('The live session %s has shown no question yet.', $this->id)));
    }

    private function checkQuiz(Quiz $quiz): void
    {
        if ($quiz->id !== $this->quiz) {
            throw QuizRefused::because(\sprintf('The live session %s plays the quiz %s, not %s.', $this->id, $this->quiz, $quiz->id));
        }
    }
}
