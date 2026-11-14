<?php

declare(strict_types=1);

namespace Tilttek\Quiz\Question;

use Tilttek\Quiz\Contract\Answer;
use Tilttek\Quiz\Exception\InvalidQuiz;
use Tilttek\Quiz\Identifier;

/**
 * The bounds every question keeps: its identifier, its prompt, its points, its time limit and its choices.
 *
 * @internal
 */
final class Rules
{
    public const int MAX_PROMPT = 1000;

    public const int MAX_POINTS = 10_000;

    public const int SHORTEST = 5;

    public const int LONGEST = 600;

    public const int FEWEST_CHOICES = 2;

    public const int MOST_CHOICES = 12;

    private function __construct()
    {
    }

    public static function check(string $id, string $prompt, int $points, ?int $timeLimit): void
    {
        Identifier::check($id, 'question');

        if ('' === trim($prompt) || \strlen($prompt) > self::MAX_PROMPT) {
            throw InvalidQuiz::because(\sprintf('The prompt of the question %s is 1 to %d bytes of text.', $id, self::MAX_PROMPT));
        }

        if ($points < 0 || $points > self::MAX_POINTS) {
            throw InvalidQuiz::because(\sprintf('The question %s is worth 0 to %d points; %d is not allowed.', $id, self::MAX_POINTS, $points));
        }

        self::checkTimeLimit($timeLimit, \sprintf('the question %s', $id));
    }

    public static function checkTimeLimit(?int $timeLimit, string $of): void
    {
        if (null !== $timeLimit && ($timeLimit < self::SHORTEST || $timeLimit > self::LONGEST)) {
            throw InvalidQuiz::because(\sprintf('The time limit of %s is %d to %d seconds; %d is not allowed.', $of, self::SHORTEST, self::LONGEST, $timeLimit));
        }
    }

    /**
     * @param list<Choice> $choices
     *
     * @return array<string, Choice> the choices by identifier
     */
    public static function choices(string $id, array $choices): array
    {
        $count = \count($choices);

        if ($count < self::FEWEST_CHOICES || $count > self::MOST_CHOICES) {
            throw InvalidQuiz::because(\sprintf('The question %s offers %d to %d choices; it offers %d.', $id, self::FEWEST_CHOICES, self::MOST_CHOICES, $count));
        }

        $byId = [];

        foreach ($choices as $choice) {
            if (isset($byId[$choice->id])) {
                throw InvalidQuiz::because(\sprintf('Two choices of the question %s are %s.', $id, $choice->id));
            }

            $byId[$choice->id] = $choice;
        }

        return $byId;
    }

    /**
     * @template T of Answer
     *
     * @param class-string<T> $kind
     *
     * @return T
     */
    public static function expect(string $id, Answer $answer, string $kind): Answer
    {
        if (!$answer instanceof $kind) {
            throw InvalidQuiz::because(\sprintf('The question %s takes a %s, not a %s.', $id, basename(str_replace('\\', '/', $kind)), basename(str_replace('\\', '/', $answer::class))));
        }

        return $answer;
    }
}
