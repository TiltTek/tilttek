<?php

declare(strict_types=1);

namespace Tilttek\Quiz\Question;

use Override;
use Tilttek\Quiz\Answer\ChoiceAnswer;
use Tilttek\Quiz\Contract\Answer;
use Tilttek\Quiz\Contract\Question;
use Tilttek\Quiz\Exception\InvalidQuiz;
use Tilttek\Quiz\Grade;

/**
 * A question with one right choice among two to twelve; true-or-false is one of them, with the choices `true` and
 * `false`. An answer that picks the right choice alone is right; anything else is wrong.
 */
final readonly class SingleChoiceQuestion implements Question
{
    /** @var array<string, Choice> */
    public array $choices;

    /**
     * @param list<Choice> $choices
     */
    public function __construct(
        private string $id,
        private string $prompt,
        array $choices,
        public string $correct,
        private int $points = 100,
        private ?int $timeLimit = null,
    ) {
        Rules::check($id, $prompt, $points, $timeLimit);
        $this->choices = Rules::choices($id, $choices);

        if (!isset($this->choices[$correct])) {
            throw InvalidQuiz::because(\sprintf('The right choice of the question %s, %s, is not one of its choices.', $id, $correct));
        }
    }

    /**
     * A true-or-false question: its choices are `true` and `false`, labelled as given.
     */
    public static function trueOrFalse(string $id, string $prompt, bool $truth, int $points = 100, ?int $timeLimit = null, string $trueLabel = 'True', string $falseLabel = 'False'): self
    {
        return new self($id, $prompt, [new Choice('true', $trueLabel), new Choice('false', $falseLabel)], $truth ? 'true' : 'false', $points, $timeLimit);
    }

    #[Override]
    public function id(): string
    {
        return $this->id;
    }

    #[Override]
    public function prompt(): string
    {
        return $this->prompt;
    }

    #[Override]
    public function points(): int
    {
        return $this->points;
    }

    #[Override]
    public function timeLimit(): ?int
    {
        return $this->timeLimit;
    }

    #[Override]
    public function grade(Answer $answer): Grade
    {
        $choices = Rules::expect($this->id, $answer, ChoiceAnswer::class)->choices;

        return [$this->correct] === $choices ? Grade::right() : Grade::wrong();
    }
}
