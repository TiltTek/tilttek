<?php

declare(strict_types=1);

namespace Tilttek\DiceBundle\Application;

/**
 * What the viewer of a roll asked for, handed to the `DiceAppearance` port: the dice as dressed, or in high contrast —
 * a setting of the viewer, or the browser's `prefers-contrast: more` or `forced-colors: active` read by the page. The
 * port leaves to the appearance what high contrast means for its dice.
 */
enum Accessibility: string
{
    /**
     * The dice as dressed, their numbers readable on their colour.
     */
    case Standard = 'standard';

    /**
     * Numbers that stand out more from their faces: solid faces, no texture, a stronger contrast.
     */
    case HighContrast = 'high-contrast';
}
