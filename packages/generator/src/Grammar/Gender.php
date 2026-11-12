<?php

declare(strict_types=1);

namespace Tilttek\Generator\Grammar;

/**
 * The grammatical gender of a word, as an entry's `gender` field writes it: `m`, `f` or `n`.
 */
enum Gender: string
{
    case Masculine = 'm';
    case Feminine = 'f';
    case Neuter = 'n';
}
