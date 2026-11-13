<?php

declare(strict_types=1);

namespace Tilttek\Heraldry;

/**
 * The charges, the figures set on the field or on an ordinary, each drawn for this package.
 */
enum Charge: string
{
    /** A star of five straight points: a mullet in English, an étoile in French. */
    case Mullet = 'mullet';
    case Crescent = 'crescent';

    /** A plain disc, named by its tincture: bezant, plate, torteau, hurt… */
    case Roundel = 'roundel';
    case Annulet = 'annulet';
    case Lozenge = 'lozenge';

    /** A lozenge voided: a macle in French. */
    case Mascle = 'mascle';

    /** A small upright rectangle: a billette in French. */
    case Billet = 'billet';
    case FleurDeLis = 'fleur_de_lis';
}
