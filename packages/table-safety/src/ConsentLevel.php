<?php

declare(strict_types=1);

namespace Tilttek\TableSafety;

use Phil\ContentWarnings\Display;

/**
 * A participant's answer to a theme of the consent checklist: green, welcome; yellow, with care or off screen; red,
 * not at this table. Red is the strictest, green the least strict.
 */
enum ConsentLevel: string
{
    case Green = 'green';
    case Yellow = 'yellow';
    case Red = 'red';

    /**
     * What the theme's warned content becomes for the table: shown, folded or hidden.
     */
    public function display(): Display
    {
        return match ($this) {
            self::Green => Display::Show,
            self::Yellow => Display::Fold,
            self::Red => Display::Hide,
        };
    }

    public function isStricterThan(self $other): bool
    {
        return match ($this) {
            self::Green => false,
            self::Yellow => self::Green === $other,
            self::Red => self::Red !== $other,
        };
    }

    /**
     * The strictest of the levels; green when none is given.
     */
    public static function strictest(self ...$levels): self
    {
        $strictest = self::Green;

        foreach ($levels as $level) {
            if ($level->isStricterThan($strictest)) {
                $strictest = $level;
            }
        }

        return $strictest;
    }
}
