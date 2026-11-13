<?php

declare(strict_types=1);

namespace Tilttek\PuzzleBuilder\Answer;

use Normalizer;
use Tilttek\PuzzleBuilder\Exception\InvalidAnswer;

/**
 * How an answer and a guess are compared: both are normalized the same way, on the server, before they meet.
 *
 * By default a text is read in its compatibility form (a full-width `Ａ` is an `a`), folded to lower case, stripped of
 * its accents, its punctuation and symbols, its spaces and its invisible characters: `Le Chêne!`, `le chene` and
 * `LECHENE` are one answer. Each step but the case folding can be turned off for a puzzle that needs it.
 */
final readonly class AnswerNormalizer
{
    /** The longest text checked, in characters: an answer, a partial answer or a guess. */
    public const int MAX_LENGTH = 200;

    public function __construct(
        public bool $keepAccents = false,
        public bool $keepSpaces = false,
        public bool $keepPunctuation = false,
    ) {
    }

    /**
     * @throws InvalidAnswer when the text is not UTF-8, is longer than `MAX_LENGTH` characters, or holds nothing once
     *                       normalized
     */
    public function normalize(string $text): string
    {
        if (!mb_check_encoding($text, 'UTF-8')) {
            throw InvalidAnswer::notText();
        }

        if (mb_strlen($text, 'UTF-8') > self::MAX_LENGTH) {
            throw InvalidAnswer::tooLong(self::MAX_LENGTH);
        }

        $text = mb_convert_case((string) Normalizer::normalize($text, Normalizer::FORM_KC), \MB_CASE_FOLD, 'UTF-8');
        $text = (string) preg_replace('/\p{C}+/u', '', $text);

        if (!$this->keepAccents) {
            $text = (string) preg_replace('/\p{M}+/u', '', (string) Normalizer::normalize($text, Normalizer::FORM_D));
        }

        if (!$this->keepPunctuation) {
            $text = (string) preg_replace('/[\p{P}\p{S}]+/u', '', $text);
        }

        $text = trim((string) preg_replace('/[\s\p{Z}]+/u', $this->keepSpaces ? ' ' : '', $text));

        if ('' === $text) {
            throw InvalidAnswer::empty();
        }

        return (string) Normalizer::normalize($text, Normalizer::FORM_C);
    }
}
