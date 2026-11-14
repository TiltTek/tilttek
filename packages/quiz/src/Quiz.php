<?php

declare(strict_types=1);

namespace Tilttek\Quiz;

use DateTimeImmutable;
use Tilttek\Quiz\Contract\Answer;
use Tilttek\Quiz\Contract\Question;
use Tilttek\Quiz\Contract\QuestionSource;
use Tilttek\Quiz\Exception\InvalidQuiz;
use Tilttek\Quiz\Question\Rules;

/**
 * A quiz: its questions in the order they are asked, the time limit of a question that sets none, and its scoring.
 */
final readonly class Quiz
{
    public const int MOST_QUESTIONS = 200;

    /** @var list<Question> */
    public array $questions;

    /**
     * @param list<Question> $questions
     * @param int|null       $timeLimit the seconds of a question that sets no time limit of its own, or null for none
     */
    public function __construct(
        public string $id,
        array $questions,
        public Scoring $scoring = new Scoring(),
        public ?int $timeLimit = null,
    ) {
        Identifier::check($id, 'quiz');
        Rules::checkTimeLimit($timeLimit, \sprintf('the quiz %s', $id));
        $count = \count($questions);

        if ($count < 1 || $count > self::MOST_QUESTIONS) {
            throw InvalidQuiz::because(\sprintf('The quiz %s asks 1 to %d questions; it asks %d.', $id, self::MOST_QUESTIONS, $count));
        }

        $seen = [];

        foreach ($questions as $question) {
            if (isset($seen[$question->id()])) {
                throw InvalidQuiz::because(\sprintf('Two questions of the quiz %s are %s.', $id, $question->id()));
            }

            $seen[$question->id()] = $question;
        }

        $this->questions = $questions;
    }

    /**
     * A quiz of up to `$count` questions drawn from a bank of the source.
     */
    public static function fromSource(string $id, QuestionSource $source, string $bank, int $count, Scoring $scoring = new Scoring(), ?int $timeLimit = null): self
    {
        if ($count < 1 || $count > self::MOST_QUESTIONS) {
            throw InvalidQuiz::because(\sprintf('The quiz %s asks 1 to %d questions; %d asked.', $id, self::MOST_QUESTIONS, $count));
        }

        return new self($id, \array_slice($source->questions($bank, $count), 0, $count), $scoring, $timeLimit);
    }

    public function count(): int
    {
        return \count($this->questions);
    }

    public function question(int $position): Question
    {
        return $this->questions[$position] ?? throw InvalidQuiz::because(\sprintf('The quiz %s has no question at position %d.', $this->id, $position));
    }

    /**
     * The seconds the question lasts: its own time limit, else the quiz's, else none.
     */
    public function limitOf(Question $question): ?int
    {
        return $question->timeLimit() ?? $this->timeLimit;
    }

    public function timer(int $position, DateTimeImmutable $shownAt): QuestionTimer
    {
        return new QuestionTimer($shownAt, $this->limitOf($this->question($position)));
    }

    /**
     * Grades and scores an answer to the question at the position, given at `$now`; once the time is up, the answer
     * is not graded and the result is timed out.
     */
    public function score(int $position, Answer $answer, QuestionTimer $timer, DateTimeImmutable $now): AnswerResult
    {
        $question = $this->question($position);
        $elapsed = $timer->elapsed($now);

        if ($timer->isOver($now)) {
            return AnswerResult::timedOut($question->id(), $elapsed);
        }

        $grade = $question->grade($answer);

        return new AnswerResult(
            $question->id(),
            $grade,
            $this->scoring->points($question->points(), $grade),
            $this->scoring->bonus($question->points(), $grade, $elapsed, $timer->limit),
            $elapsed,
        );
    }

    /**
     * The most points the quiz can give: every answer fully right, and instant in speed mode.
     */
    public function maxScore(): int
    {
        $most = 0;

        foreach ($this->questions as $question) {
            $most += $this->scoring->most($question->points(), $this->limitOf($question));
        }

        return $most;
    }
}
