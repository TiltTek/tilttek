<?php

declare(strict_types=1);

namespace Tilttek\PuzzleBuilder\Answer;

use Tilttek\PuzzleBuilder\Contract\AnswerCheck;
use Tilttek\PuzzleBuilder\Exception\InvalidAnswer;
use Tilttek\PuzzleBuilder\Exception\InvalidScenario;

/**
 * The typed answers of a puzzle: one or more accepted answers, and partial answers that tell the players to keep
 * going. Every answer is normalized once, when the key is made, and every guess the same way, then compared in
 * constant time.
 */
final readonly class AnswerKey implements AnswerCheck
{
    public const int MAX_ACCEPTED = 10;

    public const int MAX_PARTIALS = 20;

    public const int MAX_MESSAGE = 200;

    /** @var non-empty-list<string> */
    private array $accepted;

    /** @var list<array{string, string}> each partial answer normalized, with its message */
    private array $partials;

    /**
     * @param list<string>             $accepted the answers that solve the puzzle, 1 to 10
     * @param array<array-key, string> $partials the message of each partial answer, 20 at most
     *
     * @throws InvalidScenario when an answer or a message is missing, too long or empty once normalized, or a partial
     *                         answer is also accepted or given twice
     */
    public function __construct(
        array $accepted,
        array $partials = [],
        private AnswerNormalizer $normalizer = new AnswerNormalizer(),
    ) {
        if ([] === $accepted || \count($accepted) > self::MAX_ACCEPTED) {
            throw InvalidScenario::because(\sprintf('A puzzle accepts 1 to %d answers.', self::MAX_ACCEPTED));
        }

        if (\count($partials) > self::MAX_PARTIALS) {
            throw InvalidScenario::because(\sprintf('A puzzle has %d partial answers at most.', self::MAX_PARTIALS));
        }

        $normalized = [];

        foreach ($accepted as $answer) {
            $normalized[] = $this->defined($answer);
        }

        $steps = [];

        foreach ($partials as $answer => $message) {
            $key = $this->defined((string) $answer);

            if (\in_array($key, $normalized, true) || \in_array($key, array_column($steps, 0), true)) {
                throw InvalidScenario::because(\sprintf('The partial answer "%s" is accepted or given twice.', $answer));
            }

            if ('' === trim($message) || mb_strlen($message, 'UTF-8') > self::MAX_MESSAGE) {
                throw InvalidScenario::because(\sprintf('The message of a partial answer holds 1 to %d characters.', self::MAX_MESSAGE));
            }

            $steps[] = [$key, $message];
        }

        $this->accepted = $normalized;
        $this->partials = $steps;
    }

    public function check(string $guess): Verdict
    {
        $guess = $this->normalizer->normalize($guess);

        foreach ($this->accepted as $answer) {
            if (hash_equals($answer, $guess)) {
                return Verdict::correct();
            }
        }

        foreach ($this->partials as [$answer, $message]) {
            if (hash_equals($answer, $guess)) {
                return Verdict::partial($message);
            }
        }

        return Verdict::wrong();
    }

    private function defined(string $answer): string
    {
        try {
            return $this->normalizer->normalize($answer);
        } catch (InvalidAnswer $invalidAnswer) {
            throw InvalidScenario::because(\sprintf('An answer of the key is refused: %s', $invalidAnswer->getMessage()));
        }
    }
}
