<?php

declare(strict_types=1);

namespace Tilttek\Generator\Grammar;

/**
 * The article put before a word: `the`, `a`, `some`, `this` — `le`, `un`, `du`, `ce` in French.
 */
enum ArticleKind: string
{
    case Definite = 'definite';
    case Indefinite = 'indefinite';
    case Partitive = 'partitive';
    case Demonstrative = 'demonstrative';
}
