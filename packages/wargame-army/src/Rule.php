<?php

declare(strict_types=1);

namespace Tilttek\WargameArmy;

/**
 * The rule an army list breaks.
 */
enum Rule: string
{
    /** The list names another catalogue than the one it is checked against. */
    case Catalogue = 'catalogue';

    /** The list was built with another version of its catalogue. */
    case CatalogueVersion = 'catalogue-version';

    /** Fewer or more detachments than the catalogue takes. */
    case DetachmentCount = 'detachment-count';

    /** A detachment type the catalogue does not hold. */
    case UnknownDetachment = 'unknown-detachment';

    /** More detachments of a type than the catalogue allows. */
    case DetachmentLimit = 'detachment-limit';

    /** A unit the catalogue does not hold. */
    case UnknownUnit = 'unknown-unit';

    /** A unit of no category its detachment takes. */
    case NotAllowed = 'not-allowed';

    /** Fewer or more units of a category than a detachment's slot takes. */
    case Slot = 'slot';

    /** Fewer or more models than the unit takes. */
    case Models = 'models';

    /** An option the unit does not offer. */
    case UnknownOption = 'unknown-option';

    /** An option taken more times than the unit allows. */
    case OptionLimit = 'option-limit';

    /** Fewer or more selections in an option group than it takes. */
    case OptionGroup = 'option-group';

    /** More points than the list's limit. */
    case PointsLimit = 'points-limit';

    /** A constraint the catalogue declares, or one the application adds. */
    case Constraint = 'constraint';
}
