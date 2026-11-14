<?php

declare(strict_types=1);

namespace Tilttek\Quiz\SelfPaced;

use DateTimeImmutable;
use Tilttek\Quiz\AnswerResult;
use Tilttek\Quiz\Contract\Answer;
use Tilttek\Quiz\Exception\InvalidQuiz;
use Tilttek\Quiz\Exception\QuizRefused;
use Tilttek\Quiz\Identifier;
use Tilttek\Quiz\Player;
use Tilttek\Quiz\QuestionTimer;
use Tilttek\Quiz\Quiz;

/**
 * A member's run through a quiz at their own pace: the questions come one after the other, each shown when the
 * previous one is answered, and the run is over after the last. A guest never plays at their own pace: guests play in
 * a live session only.
 *
 * A play belongs to its player: an answer from anyone else is refused. Its attempt counts the member's plays of the
 * quiz: the first is the one that counts; any later one is a practice play, since its questions were seen before.
 *
 * Each change gives the play its next version, which the store checks.
 */
final class Play
{
    /** @var list<AnswerResult> */
    private array $results = [];

    private ?DateTimeImmutable $finishedAt = null;

    private function __construct(
        public readonly string $id,
        public readonly string $quiz,
        public readonly Player $player,
        public readonly DateTimeImmutable $startedAt,
        public readonly int $attempt,
        private DateTimeImmutable $shownAt,
        private int $version = 1,
    ) {
    }

    /**
     * Starts the member's play of the quiz; `$attempt` is 1 for their first play of it, and one more for each play
     * they started before.
     */
    public static function start(string $id, Quiz $quiz, Player $player, DateTimeImmutable $now, int $attempt = 1): self
    {
        Identifier::check($id, 'play');

        if ($player->isGuest()) {
            throw QuizRefused::because("A guest plays in a live session only; a quiz at one's own pace is for members.");
        }

        if ($attempt < 1) {
            throw InvalidQuiz::because(\sprintf('An attempt is counted from 1; %d is not one.', $attempt));
        }

        return new self($id, $quiz->id, $player, $now, $attempt, $now);
    }

    /**
     * Answers the current question for the player at `$now`, and shows the next one; an answer once the time is up
     * scores nothing.
     */
    public function answer(Quiz $quiz, string $player, Answer $answer, DateTimeImmutable $now): AnswerResult
    {
        if ($player !== $this->player->id) {
            throw QuizRefused::because(\sprintf('The play %s belongs to another player.', $this->id));
        }

        if ($quiz->id !== $this->quiz) {
            throw QuizRefused::because(\sprintf('The play %s is a run through the quiz %s, not %s.', $this->id, $this->quiz, $quiz->id));
        }

        if ($this->isFinished()) {
            throw QuizRefused::because(\sprintf('The play %s is over.', $this->id));
        }

        $position = \count($this->results);
        $result = $quiz->score($position, $answer, $this->timer($quiz), $now);
        $this->results[] = $result;
        ++$this->version;

        if (\count($this->results) === $quiz->count()) {
            $this->finishedAt = $now;
        } else {
            $this->shownAt = $now;
        }

        return $result;
    }

    /**
     * The timer of the current question.
     */
    public function timer(Quiz $quiz): QuestionTimer
    {
        return $quiz->timer(\count($this->results), $this->shownAt);
    }

    /** The position of the current question, or the number of questions once the play is over. */
    public function position(): int
    {
        return \count($this->results);
    }

    /**
     * Whether the member played the quiz before: the questions were seen, so the play is for practice and does not
     * count.
     */
    public function isPractice(): bool
    {
        return $this->attempt > 1;
    }

    public function isFinished(): bool
    {
        return $this->finishedAt instanceof DateTimeImmutable;
    }

    public function finishedAt(): ?DateTimeImmutable
    {
        return $this->finishedAt;
    }

    /**
     * The results of the answers given so far: the record a store keeps. What a member sees of them goes through
     * `SelfPacedQuizzes::results()`, which holds them back until the play is over.
     *
     * @return list<AnswerResult>
     */
    public function results(): array
    {
        return $this->results;
    }

    public function score(): int
    {
        return array_sum(array_map(static fn (AnswerResult $result): int => $result->total(), $this->results));
    }

    /**
     * Whether the play is over with every answer fully right and in time.
     */
    public function isPerfect(): bool
    {
        if (!$this->isFinished()) {
            return false;
        }

        return array_all($this->results, static fn (AnswerResult $result): bool => $result->grade->isRight());
    }

    public function version(): int
    {
        return $this->version;
    }
}
