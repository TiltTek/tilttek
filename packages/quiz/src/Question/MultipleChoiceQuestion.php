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
 * A question with one or more right choices. Without partial credit, the answer is right when it picks the right
 * choices exactly. With it, each right choice picked earns its share and each wrong choice picked takes one share back,
 * never below nothing.
 */
final readonly class MultipleChoiceQuestion implements Question
{
    /** @var array<string, Choice> */
    public array $choices;

    /** @var list<string> */
    public array $correct;

    /**
     * @param list<Choice> $choices
     * @param list<string> $correct
     */
    public function __construct(
        private string $id,
        private string $prompt,
        array $choices,
        array $correct,
        public bool $partialCredit = false,
        private int $points = 100,
        private ?int $timeLimit = null,
    ) {
        Rules::check($id, $prompt, $points, $timeLimit);
        $this->choices = Rules::choices($id, $choices);
        $correct = array_values(array_unique($correct));

        if ([] === $correct) {
            throw InvalidQuiz::because(\sprintf('The question %s has at least one right choice.', $id));
        }

        foreach ($correct as $choice) {
            if (!isset($this->choices[$choice])) {
                throw InvalidQuiz::because(\sprintf('The right choice %s of the question %s is not one of its choices.', $choice, $id));
            }
        }

        $this->correct = $correct;
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
        $picked = Rules::expect($this->id, $answer, ChoiceAnswer::class)->choices;
        $right = \count(array_intersect($picked, $this->correct));
        $wrong = \count($picked) - $right;

        if (!$this->partialCredit) {
            return 0 === $wrong && \count($this->correct) === $right ? Grade::right() : Grade::wrong();
        }

        return Grade::share($right - $wrong, \count($this->correct));
    }
}
