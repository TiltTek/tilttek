<?php

declare(strict_types=1);

namespace Tilttek\DiceSkins;

/**
 * What the viewer asked for: the dice as dressed, or in high contrast — a setting of the member, or the browser's
 * `prefers-contrast: more` or `forced-colors: active` read by the page.
 */
enum Accessibility: string
{
    /**
     * The look of the skin, its numbers at 4.5:1 at least.
     */
    case Standard = 'standard';

    /**
     * Numbers at 7:1 at least, solid faces, no texture.
     */
    case HighContrast = 'high-contrast';
}
