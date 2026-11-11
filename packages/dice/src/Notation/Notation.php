<?php

declare(strict_types=1);

namespace Tilttek\Dice\Notation;

use Tilttek\Dice\Exception\InvalidDefinition;

/**
 * The letters a language writes dice with: the letter of a die — `d` in English, `W` in German for *Würfel*, `T` in
 * Danish, Norwegian and Swedish for *terning* — and the words of advantage and disadvantage.
 *
 * Reading always accepts the canonical letters as well; writing uses the language's. The other modifiers keep their
 * canonical letters in every language.
 */
final readonly class Notation
{
    private const array RESERVED = ['b', 'f', 'h', 'i', 'k', 'l', 'm', 'r'];

    public function __construct(
        public string $die = 'd',
        public string $advantage = 'adv',
        public string $disadvantage = 'dis',
    ) {
        if (1 !== preg_match('/^[a-zA-Z]$/D', $die) || \in_array(strtolower($die), self::RESERVED, true)) {
            throw InvalidDefinition::because(\sprintf('The letter of a die is one letter other than b, f, h, i, k, l, m or r; "%s" is not.', $die));
        }

        foreach ([$advantage, $disadvantage] as $word) {
            if (1 !== preg_match('/^[a-zA-Z]{2,16}$/D', $word)) {
                throw InvalidDefinition::because(\sprintf('A word of advantage or disadvantage is 2 to 16 letters; "%s" is not.', $word));
            }
        }

        if (strtolower($advantage) === strtolower($disadvantage)) {
            throw InvalidDefinition::because('Advantage and disadvantage need two different words.');
        }
    }

    /**
     * Whether a letter of an input names a die in this notation, the canonical `d` included.
     */
    public function isDie(string $letter): bool
    {
        return 'd' === strtolower($letter) || strtolower($letter) === strtolower($this->die);
    }
}
