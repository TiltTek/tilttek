<?php

declare(strict_types=1);

namespace Tilttek\TableSafety\SessionZero;

/**
 * How a question of the session zero is answered: a free text, one option, or several.
 */
enum QuestionKind: string
{
    case Text = 'text';
    case Choice = 'choice';
    case Choices = 'choices';
}
