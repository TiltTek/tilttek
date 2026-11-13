<?php

declare(strict_types=1);

namespace Tilttek\TableSafety;

use Phil\ContentWarnings\Display;

/**
 * What a participant asks of a theme: a line keeps it out of the game entirely, a veil lets it happen off screen,
 * without detail. A line is the stricter of the two.
 */
enum Boundary: string
{
    case Veil = 'veil';
    case Line = 'line';

    /**
     * What the theme's warned content becomes for the table: hidden behind a line, folded behind a veil.
     */
    public function display(): Display
    {
        return match ($this) {
            self::Line => Display::Hide,
            self::Veil => Display::Fold,
        };
    }

    public function isStricterThan(self $other): bool
    {
        return self::Line === $this && self::Veil === $other;
    }
}
