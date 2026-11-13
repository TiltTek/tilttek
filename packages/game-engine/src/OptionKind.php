<?php

declare(strict_types=1);

namespace Tilttek\GameEngine;

/**
 * The kinds of value a table option takes.
 */
enum OptionKind: string
{
    case Boolean = 'bool';
    case Integer = 'int';
    case Choice = 'string';
}
