<?php

declare(strict_types=1);

namespace Tilttek\TableSafety\SessionZero;

use Phil\ContentWarnings\Key;
use Tilttek\TableSafety\Exception\InvalidAnswer;

/**
 * The questionnaire of a session zero: 1 to `MAX_QUESTIONS` questions, in order, each key once. A game master starts
 * from one of the built-in templates, then adds, removes and writes questions.
 */
final readonly class Template
{
    public const int MAX_QUESTIONS = 50;

    /**
     * @var array<string, Question> by key, in order
     */
    private array $questions;

    /**
     * @param list<Question> $questions
     */
    public function __construct(
        public string $key,
        array $questions,
    ) {
        if (!Key::isValid($key)) {
            throw InvalidAnswer::because(\sprintf('"%s" is not a template key.', InvalidAnswer::excerpt($key)));
        }

        if ([] === $questions || \count($questions) > self::MAX_QUESTIONS) {
            throw InvalidAnswer::because(\sprintf('A session zero has 1 to %d questions.', self::MAX_QUESTIONS));
        }

        $byKey = [];

        foreach ($questions as $question) {
            if (isset($byKey[$question->key])) {
                throw InvalidAnswer::because(\sprintf('The question "%s" is given twice.', $question->key));
            }

            $byKey[$question->key] = $question;
        }

        $this->questions = $byKey;
    }

    /**
     * @return list<Question> in order
     */
    public function questions(): array
    {
        return array_values($this->questions);
    }

    public function question(string $key): ?Question
    {
        return $this->questions[$key] ?? null;
    }

    /**
     * The template with the question added at its end, or put in the place of the one with its key.
     */
    public function with(Question $question): self
    {
        return new self($this->key, array_values([...$this->questions, $question->key => $question]));
    }

    /**
     * The template without the question; the last question stays.
     */
    public function without(string $key): self
    {
        $questions = $this->questions;
        unset($questions[$key]);

        return new self($this->key, array_values($questions));
    }

    /**
     * The answers the template accepts from a participant: each question's, empty ones left out. A key no question has
     * is refused.
     *
     * @param array<array-key, mixed> $answers by question key
     */
    public function answer(array $answers): Answers
    {
        foreach (array_keys($answers) as $key) {
            if (!isset($this->questions[(string) $key])) {
                throw InvalidAnswer::because(\sprintf('The session zero has no question "%s".', InvalidAnswer::excerpt((string) $key)));
            }
        }

        $accepted = [];

        foreach ($this->questions as $key => $question) {
            $answer = $question->accept($answers[$key] ?? null);

            if (null !== $answer) {
                $accepted[$key] = $answer;
            }
        }

        return new Answers($accepted);
    }

    /**
     * @return array{key: string, questions: list<array{key: string, kind: string, prompt: ?string, options: array<array-key, ?string>, required: bool}>}
     */
    public function toArray(): array
    {
        return ['key' => $this->key, 'questions' => array_map(static fn (Question $question): array => $question->toArray(), $this->questions())];
    }

    /**
     * @param array<array-key, mixed> $stored what `toArray()` returned
     */
    public static function fromArray(array $stored): self
    {
        $questions = [];

        foreach (\is_array($stored['questions'] ?? null) ? $stored['questions'] : [] as $question) {
            $questions[] = Question::fromArray(\is_array($question) ? $question : []);
        }

        return new self(\is_string($stored['key'] ?? null) ? $stored['key'] : '', $questions);
    }
}
