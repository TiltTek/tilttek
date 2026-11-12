<?php

declare(strict_types=1);

namespace Tilttek\Generator\Definition;

use Tilttek\Generator\Exception\InvalidDefinition;

/**
 * How a list invents new words from its entries by a Markov chain of letters: how many letters choose the next one,
 * the shortest and the longest word, and whether an entry may come out as it is.
 */
final readonly class MarkovChain
{
    /**
     * The longest context a chain reads: longer ones only copy the entries.
     */
    public const int MAX_ORDER = 6;

    /**
     * The longest word a chain writes, in letters.
     */
    public const int MAX_LENGTH = 64;

    /**
     * The attempts at each word a pick asks for, before the list is said exhausted.
     */
    public const int ATTEMPTS = 50;

    public function __construct(
        public int $order = 2,
        public int $minLength = 3,
        public int $maxLength = 16,
        public bool $allowEntries = false,
    ) {
        if ($order < 1 || $order > self::MAX_ORDER) {
            throw InvalidDefinition::at('markov', \sprintf('the order is a whole number from 1 to %d', self::MAX_ORDER));
        }

        if ($minLength < 1 || $maxLength < $minLength || $maxLength > self::MAX_LENGTH) {
            throw InvalidDefinition::at('markov', \sprintf('the lengths are whole numbers, the shortest at least 1 and the longest from the shortest to %d', self::MAX_LENGTH));
        }
    }
}
