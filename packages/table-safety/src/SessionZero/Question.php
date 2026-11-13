<?php

declare(strict_types=1);

namespace Tilttek\TableSafety\SessionZero;

use Phil\ContentWarnings\Key;
use Tilttek\TableSafety\Exception\InvalidAnswer;

/**
 * A question of the session zero. A question of the built-in templates has no prompt and no option labels: the
 * application translates them from their keys. A question the game master writes carries its own prompt and labels.
 *
 * A choice question has 2 to `MAX_OPTIONS` options, a text question none.
 */
final readonly class Question
{
    public const int MAX_OPTIONS = 20;

    public const int MAX_PROMPT_LENGTH = 300;

    public const int MAX_OPTION_LENGTH = 120;

    /** The longest text answer, in characters. */
    public const int MAX_ANSWER_LENGTH = 2000;

    /**
     * @param array<array-key, ?string> $options the label of each option, by key; null for a label translated from its key
     */
    public function __construct(
        public string $key,
        public QuestionKind $kind,
        public ?string $prompt = null,
        public array $options = [],
        public bool $required = false,
    ) {
        if (!Key::isValid($key)) {
            throw InvalidAnswer::because(\sprintf('"%s" is not a question key.', InvalidAnswer::excerpt($key)));
        }

        if (null !== $prompt && ('' === trim($prompt) || mb_strlen($prompt) > self::MAX_PROMPT_LENGTH)) {
            throw InvalidAnswer::because(\sprintf('The prompt of the question "%s" has 1 to %d characters.', $key, self::MAX_PROMPT_LENGTH));
        }

        $count = \count($options);

        if (QuestionKind::Text === $kind ? 0 !== $count : $count < 2 || $count > self::MAX_OPTIONS) {
            throw InvalidAnswer::because(\sprintf('The question "%s" has %s.', $key, QuestionKind::Text === $kind ? 'no option' : '2 to '.self::MAX_OPTIONS.' options'));
        }

        foreach ($options as $option => $label) {
            if (!Key::isValid((string) $option)) {
                throw InvalidAnswer::because(\sprintf('"%s" is not an option key.', InvalidAnswer::excerpt((string) $option)));
            }

            if (null !== $label && ('' === trim($label) || mb_strlen($label) > self::MAX_OPTION_LENGTH)) {
                throw InvalidAnswer::because(\sprintf('The label of the option "%s" has 1 to %d characters.', $option, self::MAX_OPTION_LENGTH));
            }
        }
    }

    /**
     * Whether the game master wrote the question, rather than the application translating it.
     */
    public function isWritten(): bool
    {
        return null !== $this->prompt;
    }

    /**
     * The answer the question accepts: a trimmed text, one option or several, or null when it is left empty.
     *
     * @return string|list<string>|null
     */
    public function accept(mixed $answer): string|array|null
    {
        $accepted = match ($this->kind) {
            QuestionKind::Text => $this->text($answer),
            QuestionKind::Choice => $this->choice($answer),
            QuestionKind::Choices => $this->choices($answer),
        };

        if ($this->required && (null === $accepted || [] === $accepted)) {
            throw InvalidAnswer::because(\sprintf('The question "%s" needs an answer.', $this->key));
        }

        return [] === $accepted ? null : $accepted;
    }

    /**
     * @return array{key: string, kind: string, prompt: ?string, options: array<array-key, ?string>, required: bool}
     */
    public function toArray(): array
    {
        return ['key' => $this->key, 'kind' => $this->kind->value, 'prompt' => $this->prompt, 'options' => $this->options, 'required' => $this->required];
    }

    /**
     * @param array<array-key, mixed> $stored what `toArray()` returned
     */
    public static function fromArray(array $stored): self
    {
        $options = [];

        foreach (\is_array($stored['options'] ?? null) ? $stored['options'] : [] as $option => $label) {
            $options[(string) $option] = \is_string($label) ? $label : null;
        }

        return new self(
            \is_string($stored['key'] ?? null) ? $stored['key'] : '',
            QuestionKind::tryFrom(\is_string($stored['kind'] ?? null) ? $stored['kind'] : '') ?? throw InvalidAnswer::because('A stored question has no kind.'),
            \is_string($stored['prompt'] ?? null) ? $stored['prompt'] : null,
            $options,
            true === ($stored['required'] ?? false),
        );
    }

    private function text(mixed $answer): ?string
    {
        if (null === $answer) {
            return null;
        }

        if (!\is_string($answer)) {
            throw InvalidAnswer::because(\sprintf('The question "%s" takes a text.', $this->key));
        }

        $text = trim($answer);

        if (mb_strlen($text) > self::MAX_ANSWER_LENGTH) {
            throw InvalidAnswer::because(\sprintf('The answer to the question "%s" has at most %d characters.', $this->key, self::MAX_ANSWER_LENGTH));
        }

        return '' === $text ? null : $text;
    }

    private function choice(mixed $answer): ?string
    {
        if (null === $answer || '' === $answer) {
            return null;
        }

        if (!\is_string($answer) || !\array_key_exists($answer, $this->options)) {
            throw InvalidAnswer::because(\sprintf('The question "%s" has no such option.', $this->key));
        }

        return $answer;
    }

    /**
     * @return list<string>
     */
    private function choices(mixed $answer): array
    {
        if (null === $answer) {
            return [];
        }

        if (!\is_array($answer)) {
            throw InvalidAnswer::because(\sprintf('The question "%s" takes a list of options.', $this->key));
        }

        $chosen = [];

        foreach ($answer as $option) {
            if (!\is_string($option) || !\array_key_exists($option, $this->options)) {
                throw InvalidAnswer::because(\sprintf('The question "%s" has no such option.', $this->key));
            }

            $chosen[$option] = true;
        }

        // In the order of the options, each once.
        return array_values(array_filter(array_map(strval(...), array_keys($this->options)), static fn (string $option): bool => isset($chosen[$option])));
    }
}
