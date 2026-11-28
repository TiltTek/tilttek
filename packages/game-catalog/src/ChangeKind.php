<?php

declare(strict_types=1);

namespace Tilttek\GameCatalog;

/**
 * What changed an entry, in its history.
 */
enum ChangeKind: string
{
    /** Added, by hand or by a source. */
    case Created = 'created';
    /** Its source sent it again. */
    case Synchronized = 'synchronized';
    /** A correction was accepted. */
    case Corrected = 'corrected';
    /** A corrected field went back to the value of the source. */
    case Reverted = 'reverted';
    /** Hidden from the catalogue. */
    case Hidden = 'hidden';
    /** Shown again. */
    case Shown = 'shown';
    /** An erratum was published. */
    case ErratumPublished = 'erratum_published';
    /** An identifier of an open database was added. */
    case IdentifierAdded = 'identifier_added';
    /** A barcode was given to it. */
    case BarcodeAssigned = 'barcode_assigned';
}
