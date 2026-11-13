<?php

declare(strict_types=1);

namespace Tilttek\Heraldry;

/**
 * The outline the arms are drawn in. It changes the drawing, never the blazon.
 */
enum ShieldShape: string
{
    /** Straight sides curving to a point: the most common shield. */
    case Heater = 'heater';

    /** Straight sides, a flat base with rounded corners and a small point in the middle. */
    case French = 'french';

    /** Straight sides and a round base. */
    case Iberian = 'iberian';

    /** A lozenge, standing on one of its points. */
    case Lozenge = 'lozenge';
}
