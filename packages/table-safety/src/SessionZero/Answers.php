<?php

declare(strict_types=1);

namespace Tilttek\TableSafety\SessionZero;

use Tilttek\TableSafety\Exception\InvalidAnswer;

/**
 * A participant's answers to the session zero, as `Template::answer()` accepted them: a text, an option or a list of
 * options, by question key. A question left empty has no entry.
 */
final readonly class Answers
{
    /**
     * @param array<string, string|list<string>> $answers by question key
     */
    public function __construct(
        private array $answers = [],
    ) {
    }

    public static function none(): self
    {
        return new self();
    }

    /**
     * @return string|list<string>|null
     */
    public function of(string $question): string|array|null
    {
        return $this->answers[$question] ?? null;
    }

    public function isEmpty(): bool
    {
        return [] === $this->answers;
    }

    /**
     * @return array<string, string|list<string>> by question key
     */
    public function toArray(): array
    {
        return $this->answers;
    }

    /**
     * Answers read back from storage, before a template checks them again.
     *
     * @param array<array-key, mixed> $stored what `toArray()` returned
     */
    public static function fromArray(array $stored): self
    {
        $answers = [];

        foreach ($stored as $question => $answer) {
            $answers[(string) $question] = \is_string($answer) ? $answer : self::options($answer) ?? throw InvalidAnswer::because(\sprintf('The stored answer to "%s" is neither a text nor a list of options.', InvalidAnswer::excerpt((string) $question)));
        }

        return new self($answers);
    }

    /**
     * @return list<string>|null
     */
    private static function options(mixed $answer): ?array
    {
        if (!\is_array($answer) || !array_is_list($answer)) {
            return null;
        }

        $options = [];

        foreach ($answer as $option) {
            if (!\is_string($option)) {
                return null;
            }

            $options[] = $option;
        }

        return $options;
    }
}
