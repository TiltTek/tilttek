<?php

declare(strict_types=1);

namespace Tilttek\TableSafety\SessionZero;

use Tilttek\TableSafety\Exception\InvalidAnswer;
use Tilttek\TableSafety\RespondentThreshold;

/**
 * The pact of a table: its participants' answers to the session zero, gathered without saying who gave which. For a
 * choice question, how many chose each option; for a text question, the answers in alphabetical order rather than in
 * the order they were given.
 *
 * A pact holds answers only in whole groups of `$threshold` participants, taken in the order they were given: under the
 * threshold it holds none — every tally is at 0, every list of texts empty — and says only that there are not enough
 * answers; past it, the answers of a group that is not full wait for the group to fill. One more answer therefore never
 * changes the pact by the answers of one person alone.
 */
final readonly class Pact
{
    /**
     * @param array<string, array<string, int>> $tallies     by question key, then option key
     * @param array<string, list<string>>       $texts       by question key
     * @param int                               $respondents how many participants' answers the pact holds: 0, or a multiple of the threshold
     * @param positive-int                      $threshold
     */
    private function __construct(
        public Template $template,
        public int $respondents,
        private array $tallies,
        private array $texts,
        public int $threshold,
    ) {
    }

    /**
     * @param list<Answers> $answers each participant's, in the order they were given; answers to none of the template's
     *                               questions are not an answer and take no place in a group
     *
     * @throws InvalidAnswer when the threshold is under 1
     */
    public static function of(Template $template, array $answers, int $threshold = RespondentThreshold::DEFAULT): self
    {
        RespondentThreshold::assert($threshold);

        $tallies = [];
        $texts = [];

        foreach ($template->questions() as $question) {
            if (QuestionKind::Text === $question->kind) {
                $texts[$question->key] = [];
            } else {
                $tallies[$question->key] = array_fill_keys(array_map(strval(...), array_keys($question->options)), 0);
            }
        }

        $answered = array_filter($answers, static fn (Answers $participant): bool => self::answers($template, $participant));
        $respondents = RespondentThreshold::counted(\count($answered), $threshold);

        foreach (\array_slice($answered, 0, $respondents) as $participant) {
            self::count($template, $participant, $tallies, $texts);
        }

        foreach ($texts as $key => $list) {
            sort($list, \SORT_STRING | \SORT_FLAG_CASE);
            $texts[$key] = $list;
        }

        return new self($template, $respondents, $tallies, $texts, $threshold);
    }

    /**
     * Whether the pact holds any answer; false means "not enough answers yet".
     */
    public function hasEnoughAnswers(): bool
    {
        return $this->respondents > 0;
    }

    /**
     * Whether the participant answered at least one of the template's questions.
     */
    private static function answers(Template $template, Answers $participant): bool
    {
        return array_any($template->questions(), static fn (Question $question): bool => null !== $participant->of($question->key));
    }

    /**
     * Counts a participant's answers in the tallies and the texts.
     *
     * @param array<string, array<string, int>> $tallies
     * @param array<string, list<string>>       $texts
     */
    private static function count(Template $template, Answers $participant, array &$tallies, array &$texts): void
    {
        foreach ($template->questions() as $question) {
            $answer = $participant->of($question->key);

            if (null === $answer) {
                continue;
            }

            if (isset($texts[$question->key])) {
                $texts[$question->key][] = \is_string($answer) ? $answer : implode(', ', $answer);

                continue;
            }

            foreach ((array) $answer as $option) {
                if (isset($tallies[$question->key][$option])) {
                    ++$tallies[$question->key][$option];
                }
            }
        }
    }

    /**
     * How many chose each option of a choice question, by option key in the question's order; empty for another.
     *
     * @return array<string, int>
     */
    public function tally(string $question): array
    {
        return $this->tallies[$question] ?? [];
    }

    /**
     * The answers to a text question, in alphabetical order; empty for another.
     *
     * @return list<string>
     */
    public function texts(string $question): array
    {
        return $this->texts[$question] ?? [];
    }
}
