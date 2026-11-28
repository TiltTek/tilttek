<?php

declare(strict_types=1);

namespace Tilttek\GameCatalog\Contract;

use Tilttek\GameCatalog\CorrectionStatus;

/**
 * Told of what the members of a site may want to know, by identifiers only: an application sends a message or a
 * notification from them, and reads the rest from the catalogue.
 */
interface CatalogObserver
{
    /**
     * An erratum was published on the entry: the moment to tell the members who follow the game.
     */
    public function erratumPublished(string $entry, string $erratum): void;

    /**
     * A member proposed a correction: the moment to tell the administration.
     */
    public function correctionProposed(string $entry, string $correction): void;

    /**
     * The administration accepted or refused a correction: the moment to tell the member.
     */
    public function correctionDecided(string $correction, string $member, CorrectionStatus $status): void;
}
