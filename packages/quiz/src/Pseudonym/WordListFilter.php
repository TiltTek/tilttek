<?php

declare(strict_types=1);

/*
 * Content warning: the lists below hold insults, slurs and vulgar words in English and in French, in plain text, so
 * that the pseudonyms holding them are refused.
 */

namespace Tilttek\Quiz\Pseudonym;

use Normalizer;
use Override;
use Tilttek\Quiz\Contract\PseudonymFilter;
use Tilttek\Quiz\Exception\InvalidQuiz;

/**
 * Refuses the pseudonyms that hold a word of its lists. Both the pseudonym and the words are folded first: lower case,
 * without accents, the usual digit and symbol stand-ins read as letters ("4dm1n" is "admin"), and everything but
 * letters left out. A word of `words` is refused as a whole word of the pseudonym, or as the whole pseudonym once its
 * words are joined ("ad min"); a word of `anywhere` is refused wherever it sits, for the terms no common word holds.
 *
 * `defaults()` holds a short list of the library's own, in English and in French: the names that would pass a guest
 * for the host or the staff, and common insults. It is no moderation: an application with a fuller list of its own,
 * or a moderation service, adds it with `with()` or `ChainFilter`.
 */
final readonly class WordListFilter implements PseudonymFilter
{
    /** The most words both lists hold together. */
    public const int MOST_WORDS = 10_000;

    /** The longest word, and the longest text read, in characters: anything longer is refused. */
    public const int LONGEST = 64;

    private const array STAND_INS = ['0' => 'o', '1' => 'i', '3' => 'e', '4' => 'a', '5' => 's', '7' => 't', '@' => 'a', '$' => 's', '!' => 'i', '|' => 'i'];

    /** The library's own whole words: names of the host and the staff, then insults. */
    private const array WORDS = [
        'admin', 'administrator', 'administrateur', 'administratrice', 'moderator', 'moderateur', 'moderatrice', 'modo',
        'host', 'animateur', 'animatrice', 'staff', 'system', 'systeme', 'root', 'support', 'official', 'officiel',
        'ass', 'asshole', 'dick', 'cock', 'pussy', 'cunt', 'slut', 'fag', 'rape', 'nazi', 'hitler',
        'merde', 'pute', 'salope', 'batard', 'ostie', 'hostie', 'crisse', 'calice', 'viarge', 'fif',
    ];

    /** The library's own terms refused wherever they sit, which no common word holds. */
    private const array ANYWHERE = [
        'fuck', 'shit', 'bitch', 'whore', 'faggot', 'nigger', 'nigga',
        'putain', 'connard', 'connasse', 'encule', 'tabarnak', 'tabarnac', 'calisse', 'ciboire',
    ];

    /** @var array<string, string> the words, folded, by themselves */
    private array $words;

    /** @var array<string, string> the terms refused anywhere, folded, by themselves */
    private array $anywhere;

    /**
     * @param list<string> $words    refused as whole words
     * @param list<string> $anywhere refused wherever they sit
     */
    public function __construct(array $words, array $anywhere = [])
    {
        if (\count($words) + \count($anywhere) > self::MOST_WORDS) {
            throw InvalidQuiz::because(\sprintf('A word list holds at most %d words.', self::MOST_WORDS));
        }

        $this->words = $this->set($words);
        $this->anywhere = $this->set($anywhere);
    }

    /**
     * The library's short list.
     */
    public static function defaults(): self
    {
        return new self(self::WORDS, self::ANYWHERE);
    }

    /**
     * This list and the application's words.
     *
     * @param list<string> $words    refused as whole words
     * @param list<string> $anywhere refused wherever they sit
     */
    public function with(array $words, array $anywhere = []): self
    {
        return new self([...array_values($this->words), ...$words], [...array_values($this->anywhere), ...$anywhere]);
    }

    #[Override]
    public function allows(string $pseudonym): bool
    {
        if (!mb_check_encoding($pseudonym, 'UTF-8') || mb_strlen($pseudonym, 'UTF-8') > self::LONGEST) {
            return false;
        }

        $parts = $this->parts($pseudonym);
        $joined = implode('', $parts);

        if (isset($this->words[$joined]) || array_any($parts, fn (string $part): bool => isset($this->words[$part]))) {
            return false;
        }

        return !array_any($this->anywhere, static fn (string $term): bool => str_contains($joined, $term));
    }

    /**
     * @param list<string> $words
     *
     * @return array<string, string>
     */
    private function set(array $words): array
    {
        $set = [];

        foreach ($words as $word) {
            if (!mb_check_encoding($word, 'UTF-8') || mb_strlen($word, 'UTF-8') > self::LONGEST) {
                throw InvalidQuiz::because(\sprintf('A word of the list is text of at most %d characters.', self::LONGEST));
            }

            $folded = implode('', $this->parts($word));

            if ('' === $folded) {
                throw InvalidQuiz::because('A word of the list holds at least one letter.');
            }

            $set[$folded] = $folded;
        }

        return $set;
    }

    /**
     * The words of a text, folded: lower case, without accents, stand-ins read as letters, letters only.
     *
     * @return list<string>
     */
    private function parts(string $text): array
    {
        $decomposed = (string) Normalizer::normalize(mb_strtolower($text, 'UTF-8'), Normalizer::FORM_KD);
        $folded = strtr((string) preg_replace('/\p{Mn}+/u', '', $decomposed), self::STAND_INS);
        $parts = preg_split('/\P{L}+/u', $folded, -1, \PREG_SPLIT_NO_EMPTY);

        return false === $parts ? [] : $parts;
    }
}
