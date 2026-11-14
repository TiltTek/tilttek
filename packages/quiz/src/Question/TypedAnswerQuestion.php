<?php

declare(strict_types=1);

namespace Tilttek\Quiz\Question;

use Normalizer;
use Override;
use Tilttek\Quiz\Answer\TextAnswer;
use Tilttek\Quiz\Contract\Answer;
use Tilttek\Quiz\Contract\Question;
use Tilttek\Quiz\Exception\InvalidQuiz;
use Tilttek\Quiz\Grade;

/**
 * A question answered by typing, right when the words typed match one of the accepted answers. Both are compared
 * folded: case ignored, spaces trimmed and collapsed, and accents ignored unless the question keeps them.
 */
final readonly class TypedAnswerQuestion implements Question
{
    public const int MOST_ACCEPTED = 20;

    /** @var list<string> */
    public array $accepted;

    /** @var array<string, string> the accepted answers, folded, by themselves */
    private array $folded;

    /**
     * @param list<string> $accepted
     */
    public function __construct(
        private string $id,
        private string $prompt,
        array $accepted,
        public bool $keepAccents = false,
        private int $points = 100,
        private ?int $timeLimit = null,
    ) {
        Rules::check($id, $prompt, $points, $timeLimit);
        $count = \count($accepted);

        if ($count < 1 || $count > self::MOST_ACCEPTED) {
            throw InvalidQuiz::because(\sprintf('The question %s accepts 1 to %d answers; it accepts %d.', $id, self::MOST_ACCEPTED, $count));
        }

        $folded = [];

        foreach ($accepted as $answer) {
            $key = $this->fold(new TextAnswer($answer)->text);

            if ('' === $key) {
                throw InvalidQuiz::because(\sprintf('An accepted answer of the question %s is empty.', $id));
            }

            $folded[$key] = $key;
        }

        $this->accepted = $accepted;
        $this->folded = $folded;
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
        $text = Rules::expect($this->id, $answer, TextAnswer::class)->text;

        return isset($this->folded[$this->fold($text)]) ? Grade::right() : Grade::wrong();
    }

    /**
     * The text in lower case, its runs of spaces made one and trimmed, without its accents unless they are kept.
     */
    public function fold(string $text): string
    {
        $text = mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $text)), 'UTF-8');

        if (!$this->keepAccents) {
            $text = (string) preg_replace('/\p{Mn}+/u', '', (string) Normalizer::normalize($text, Normalizer::FORM_D));
        }

        return (string) Normalizer::normalize($text, Normalizer::FORM_C);
    }
}
