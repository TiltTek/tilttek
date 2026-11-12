<?php

declare(strict_types=1);

namespace Tilttek\WritingGoals;

/**
 * What a goal counts.
 */
enum Unit: string
{
    case Words = 'words';

    case Pages = 'pages';
}
