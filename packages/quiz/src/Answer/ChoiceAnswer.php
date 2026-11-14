<?php

declare(strict_types=1);

namespace Tilttek\Quiz\Answer;

use Tilttek\Quiz\Contract\Answer;
use Tilttek\Quiz\Exception\InvalidQuiz;
use Tilttek\Quiz\Identifier;

/**
 * The choices a player picks, by identifier: one for a single-choice or true-or-false question, any number for a
 * multiple-choice one. Each choice counts once, whatever the order.
 */
final readonly class ChoiceAnswer implements Answer
{
    /** The most choices an answer holds: more than any question offers. */
    public const int MAX_CHOICES = 32;

    /** @var list<string> */
    public array $choices;

    /**
     * @param array<string> $choices
     */
    public function __construct(array $choices)
    {
        if (\count($choices) > self::MAX_CHOICES) {
            throw InvalidQuiz::because(\sprintf('An answer picks at most %d choices; this one picks %d.', self::MAX_CHOICES, \count($choices)));
        }

        foreach ($choices as $choice) {
            Identifier::check($choice, 'choice');
        }

        $this->choices = array_values(array_unique($choices));
    }

    public static function of(string ...$choices): self
    {
        return new self($choices);
    }
}
