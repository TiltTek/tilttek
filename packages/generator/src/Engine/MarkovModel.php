<?php

declare(strict_types=1);

namespace Tilttek\Generator\Engine;

use LogicException;
use Random\Randomizer;
use Tilttek\Generator\Definition\Entry;
use Tilttek\Generator\Definition\MarkovChain;

/**
 * What a Markov list has learnt from its entries: for each run of letters as long as the chain's order, the letters
 * that follow it and how often, counted by the weight of the entries. Words are written letter by letter from it, in
 * lower case, and capitalised when every entry is.
 *
 * @internal
 */
final readonly class MarkovModel
{
    /**
     * The mark before the first letter of a word.
     */
    private const string START = "\u{2}";

    /**
     * The mark after the last letter of a word.
     */
    private const string END = "\u{3}";

    /**
     * @param array<string, array<array-key, int>> $next  by run of letters, the letters that follow and their counts
     * @param array<string, true>                  $known the entries, in lower case
     */
    private function __construct(
        public MarkovChain $chain,
        private array $next,
        private array $known,
        private bool $capitalised,
    ) {
    }

    /**
     * @param list<Entry> $entries
     */
    public static function learn(MarkovChain $chain, array $entries): self
    {
        $next = [];
        $known = [];
        $capitalised = [] !== $entries;

        foreach ($entries as $entry) {
            $text = preg_replace('/\p{C}/u', '', $entry->text) ?? '';
            $first = mb_substr($text, 0, 1);
            $capitalised = $capitalised && $first !== mb_strtolower($first);
            $text = mb_strtolower($text);
            $known[$text] = true;
            $letters = [...array_pad([], $chain->order, self::START), ...mb_str_split($text), self::END];

            for ($index = $chain->order, $length = \count($letters); $index < $length; ++$index) {
                $run = implode('', \array_slice($letters, $index - $chain->order, $chain->order));
                $next[$run][$letters[$index]] = ($next[$run][$letters[$index]] ?? 0) + $entry->weight;
            }
        }

        return new self($chain, $next, $known, $capitalised);
    }

    /**
     * One attempt at a word within the chain's lengths, or null when the letters lead nowhere within them.
     */
    public function word(Randomizer $randomizer): ?string
    {
        $run = str_repeat(self::START, $this->chain->order);
        $letters = [];

        while (true) {
            $followers = $this->next[$run] ?? [];
            $length = \count($letters);

            if ($length < $this->chain->minLength) {
                unset($followers[self::END]);
            }

            if ($length >= $this->chain->maxLength) {
                $followers = array_intersect_key($followers, [self::END => true]);
            }

            $letter = $this->choose($followers, $randomizer);

            if (null === $letter) {
                return null;
            }

            if (self::END === $letter) {
                $word = implode('', $letters);

                return $this->capitalised ? mb_strtoupper(mb_substr($word, 0, 1)).mb_substr($word, 1) : $word;
            }

            $letters[] = $letter;
            $run = mb_substr($run, 1).$letter;
        }
    }

    /**
     * Whether a word, in lower case, is one of the entries.
     */
    public function knows(string $word): bool
    {
        return isset($this->known[$word]);
    }

    /**
     * A letter chosen by its count; the only one takes no randomness. A digit is a key PHP turns into an integer.
     *
     * @param array<array-key, int> $followers
     */
    private function choose(array $followers, Randomizer $randomizer): ?string
    {
        if (\count($followers) < 2) {
            $only = array_key_first($followers);

            return null === $only ? null : (string) $only;
        }

        $roll = $randomizer->getInt(1, array_sum($followers));

        foreach ($followers as $letter => $count) {
            $roll -= $count;

            if ($roll <= 0) {
                return (string) $letter;
            }
        }

        throw new LogicException('A choice by count always finds a letter.');
    }
}
