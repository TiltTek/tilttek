<?php

declare(strict_types=1);

namespace Tilttek\RulesLookup\Store;

use Tilttek\RpgSystems\Translations;
use Tilttek\RulesLookup\Contract\RuleStore;
use Tilttek\RulesLookup\Exception\InvalidRule;
use Tilttek\RulesLookup\Folding;
use Tilttek\RulesLookup\Rule;
use Tilttek\RulesLookup\RuleId;
use Tilttek\RulesLookup\RuleMatch;
use Tilttek\RulesLookup\RuleMatches;
use Tilttek\RulesLookup\RuleQuery;

/**
 * The rules kept in memory, their words folded and indexed once when the store is made: what an application reads
 * from data packages at warm-up, and what tests use. The store never changes once made, so a worker shares it between
 * requests; `with()` gives a new store.
 *
 * Scoring: each word found in the title counts 10, as the start of a title word 6, in the text 3, as the start of a
 * text word 1 — the start of a word only for the last word typed, of two characters or more —; a title equal to the
 * words searched adds 100, a title that starts with them 20. Ties go to the title, then to the identifier.
 */
final readonly class InMemoryRuleStore implements RuleStore
{
    /** The most rules one store holds. */
    public const int MAX_RULES = 100_000;

    /** The most words the start of a word stands for. */
    private const int MAX_EXPANSIONS = 64;

    /** @var array<string, Rule> by identifier */
    private array $rules;

    /** @var array<string, array<string, array<string, true>>> the words of each title, by identifier and locale */
    private array $titleWords;

    /** @var array<string, array<string, array<string, true>>> the words of each text, by identifier and locale */
    private array $textWords;

    /** @var array<string, array<string, string>> each title folded, by identifier and locale */
    private array $phrases;

    /** @var list<string> every word of every title and text, sorted */
    private array $vocabulary;

    /** @var array<string, array<string, true>> the identifiers of the rules that hold a word, in any title or text */
    private array $postings;

    /** @var array<string, list<string>> the identifiers of the rules that refer to a rule, by its identifier */
    private array $referrers;

    /**
     * @throws InvalidRule when two rules share an identifier, or the store would hold more than `MAX_RULES`
     */
    public function __construct(Rule ...$rules)
    {
        if (\count($rules) > self::MAX_RULES) {
            throw InvalidRule::because(\sprintf('A store holds at most %d rules.', self::MAX_RULES));
        }

        $byId = [];
        $titleWords = [];
        $textWords = [];
        $phrases = [];
        $postings = [];
        $referrers = [];

        foreach ($rules as $rule) {
            $id = $rule->id->toString();

            if (isset($byId[$id])) {
                throw InvalidRule::because(\sprintf('Two rules have the identifier "%s".', $id));
            }

            $byId[$id] = $rule;

            foreach ($rule->titles->texts as $locale => $title) {
                $words = Folding::words($title);
                $titleWords[$id][$locale] = array_fill_keys($words, true);
                $phrases[$id][$locale] = implode(' ', $words);

                foreach ($words as $word) {
                    $postings[$word][$id] = true;
                }
            }

            foreach ($rule->texts->texts as $locale => $text) {
                $words = Folding::words($text);
                $textWords[$id][$locale] = array_fill_keys($words, true);

                foreach ($words as $word) {
                    $postings[$word][$id] = true;
                }
            }

            foreach ($rule->references as $reference) {
                $referrers[$reference->toString()][] = $id;
            }
        }

        $words = array_map(strval(...), array_keys($postings));
        sort($words, \SORT_STRING);

        $this->rules = $byId;
        $this->titleWords = $titleWords;
        $this->textWords = $textWords;
        $this->phrases = $phrases;
        $this->vocabulary = $words;
        $this->postings = $postings;
        $this->referrers = $referrers;
    }

    /**
     * A store with these rules added, a rule of the same identifier replaced.
     *
     * @throws InvalidRule
     */
    public function with(Rule ...$rules): self
    {
        $all = $this->rules;

        foreach ($rules as $rule) {
            $all[$rule->id->toString()] = $rule;
        }

        return new self(...array_values($all));
    }

    public function get(RuleId $id): ?Rule
    {
        return $this->rules[$id->toString()] ?? null;
    }

    /**
     * @return list<Rule>
     */
    public function all(): array
    {
        return array_values($this->rules);
    }

    public function search(RuleQuery $query): RuleMatches
    {
        $words = $query->words;
        $last = array_key_last($words);
        $starts = null !== $last && mb_strlen($words[$last]) >= 2 ? array_flip($this->expand($words[$last])) : [];
        $phrase = $query->phrase();
        $scores = [];
        $titles = [];
        $ids = [];
        $rules = [];

        foreach ($this->candidates($words, $starts) as $id => $rule) {
            if (!$this->filtered($rule, $query)) {
                continue;
            }

            $titleLocale = $this->chosen($rule->titles, $query->locale);
            $title = $this->titleWords[$id][$titleLocale] ?? [];
            $text = $this->textWords[$id][$this->chosen($rule->texts, $query->locale)] ?? [];
            $score = 0;

            foreach ($words as $index => $word) {
                $points = $this->points($word, $index === $last ? $starts : [], $title, $text);

                if (0 === $points) {
                    continue 2;
                }

                $score += $points;
            }

            $folded = $this->phrases[$id][$titleLocale] ?? '';

            if ('' !== $phrase) {
                $score += match (true) {
                    $folded === $phrase => 100,
                    str_starts_with($folded, $phrase.' ') => 20,
                    default => 0,
                };
            }

            $scores[] = $score;
            $titles[] = $folded;
            $ids[] = $id;
            $rules[] = $rule;
        }

        // Identifiers are unique, so the rules themselves are never compared.
        array_multisort($scores, \SORT_DESC, \SORT_NUMERIC, $titles, \SORT_ASC, \SORT_STRING, $ids, \SORT_ASC, \SORT_STRING, $rules);

        $matches = [];

        foreach (\array_slice($rules, $query->offset, $query->limit, true) as $position => $rule) {
            $matches[] = new RuleMatch($rule, $scores[$position]);
        }

        return new RuleMatches($matches, \count($rules));
    }

    /**
     * The rules that hold every word somewhere — in a title or a text of any locale, the last word or a word it
     * starts —, found by the postings rather than by reading every rule; every rule when no word is searched.
     *
     * @param list<string>       $words
     * @param array<string, int> $starts the words the last word starts, as keys
     *
     * @return array<string, Rule>
     */
    private function candidates(array $words, array $starts): array
    {
        $candidates = null;
        $last = array_key_last($words);

        foreach ($words as $index => $word) {
            $ids = $this->postings[$word] ?? [];

            if ($index === $last) {
                foreach (array_keys($starts) as $start) {
                    $ids += $this->postings[$start] ?? [];
                }
            }

            $candidates = null === $candidates ? $ids : array_intersect_key($candidates, $ids);
        }

        return null === $candidates ? $this->rules : array_intersect_key($this->rules, $candidates);
    }

    public function referencing(RuleId $id): array
    {
        return array_map(fn (string $referrer): Rule => $this->rules[$referrer], $this->referrers[$id->toString()] ?? []);
    }

    private function filtered(Rule $rule, RuleQuery $query): bool
    {
        return (null === $query->system || $rule->id->system === $query->system)
            && (null === $query->version || $rule->id->version === $query->version)
            && (null === $query->category || $rule->category === $query->category);
    }

    /**
     * The points of one word: 10 in the title, 6 as the start of a title word, 3 in the text, 1 as the start of a text
     * word, 0 when it is in neither.
     *
     * @param array<string, int>  $starts the words the word starts, as keys, when it is the last word typed
     * @param array<string, true> $title
     * @param array<string, true> $text
     */
    private function points(string $word, array $starts, array $title, array $text): int
    {
        return match (true) {
            isset($title[$word]) => 10,
            [] !== array_intersect_key($title, $starts) => 6,
            isset($text[$word]) => 3,
            [] !== array_intersect_key($text, $starts) => 1,
            default => 0,
        };
    }

    /**
     * The words of the store that start with a word and are longer than it, at most `MAX_EXPANSIONS`, found by a
     * binary search in the sorted vocabulary.
     *
     * @return list<string>
     */
    private function expand(string $prefix): array
    {
        $low = 0;
        $high = \count($this->vocabulary);

        while ($low < $high) {
            $middle = intdiv($low + $high, 2);

            if (strcmp($this->vocabulary[$middle], $prefix) < 0) {
                $low = $middle + 1;
            } else {
                $high = $middle;
            }
        }

        $words = [];

        for ($index = $low; isset($this->vocabulary[$index]) && \count($words) < self::MAX_EXPANSIONS && str_starts_with($this->vocabulary[$index], $prefix); ++$index) {
            if ($this->vocabulary[$index] !== $prefix) {
                $words[] = $this->vocabulary[$index];
            }
        }

        return $words;
    }

    /**
     * The locale whose text a reader sees, as `Translations::in()` chooses it: the locale, its language, `en`, then
     * the first; an empty string for no text.
     */
    private function chosen(Translations $translations, string $locale): string
    {
        $texts = $translations->texts;
        $language = explode('_', $locale)[0];

        return match (true) {
            isset($texts[$locale]) => $locale,
            isset($texts[$language]) => $language,
            isset($texts['en']) => 'en',
            default => (string) array_key_first($texts),
        };
    }
}
