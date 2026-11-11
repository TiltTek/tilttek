<?php

declare(strict_types=1);

namespace Tilttek\Progression;

/**
 * Whom a track's experience goes to: the member who made the gesture, or the gesture's object — a character, whose
 * experience follows the rules of its game.
 */
enum Subject: string
{
    case Member = 'member';

    case Object = 'object';
}
