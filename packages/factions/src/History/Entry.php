<?php

declare(strict_types=1);

namespace Tilttek\Factions\History;

use DateTimeImmutable;
use Tilttek\Factions\Reason;

/**
 * One change of the history of a roster: what faction it touched, why, who made it and when. The store keeps it with
 * the roster, in the same write.
 */
interface Entry
{
    /** The identifier of the campaign. */
    public string $campaign { get; }

    /** The key of the faction the change touched; of the pair's first faction for a relation. */
    public string $faction { get; }

    public Reason $reason { get; }

    /** The identifier of the member who made the change. */
    public string $by { get; }

    public DateTimeImmutable $at { get; }

    /**
     * Whether the change touched the faction of the key.
     */
    public function concerns(string $faction): bool;
}
