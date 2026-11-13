<?php

declare(strict_types=1);

namespace Tilttek\TableSafety\SessionZero;

/**
 * The pact of a table: its participants' answers to the session zero, gathered without saying who gave which. For a
 * choice question, how many chose each option; for a text question, the answers in alphabetical order rather than in
 * the order they were given.
 */
final readonly class Pact
{
    /**
     * @param array<string, array<string, int>> $tallies by question key, then option key
     * @param array<string, list<string>>       $texts   by question key
     */
    private function __construct(
        public Template $template,
        public int $respondents,
        private array $tallies,
        private array $texts,
    ) {
    }

    /**
     * @param list<Answers> $answers each participant's
     */
    public static function of(Template $template, array $answers): self
    {
        $tallies = [];
        $texts = [];
        $respondents = 0;

        foreach ($template->questions() as $question) {
            if (QuestionKind::Text === $question->kind) {
                $texts[$question->key] = [];
            } else {
                $tallies[$question->key] = array_fill_keys(array_map(strval(...), array_keys($question->options)), 0);
            }
        }

        foreach ($answers as $participant) {
            $respondents += self::count($template, $participant, $tallies, $texts) ? 1 : 0;
        }

        foreach ($texts as $key => $list) {
            sort($list, \SORT_STRING | \SORT_FLAG_CASE);
            $texts[$key] = $list;
        }

        return new self($template, $respondents, $tallies, $texts);
    }

    /**
     * Counts a participant's answers in the tallies and the texts; whether they answered anything.
     *
     * @param array<string, array<string, int>> $tallies
     * @param array<string, list<string>>       $texts
     */
    private static function count(Template $template, Answers $participant, array &$tallies, array &$texts): bool
    {
        $answered = false;

        foreach ($template->questions() as $question) {
            $answer = $participant->of($question->key);

            if (null === $answer) {
                continue;
            }

            $answered = true;

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

        return $answered;
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
