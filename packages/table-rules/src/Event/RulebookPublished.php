<?php

declare(strict_types=1);

namespace Tilttek\TableRules\Event;

/**
 * A new version of the table's rules was published.
 */
final readonly class RulebookPublished implements RulesEvent
{
    /**
     * @param int  $version            the number of the version
     * @param bool $requiresAcceptance whether every player accepts it anew
     */
    public function __construct(public string $table, public int $version, public bool $requiresAcceptance)
    {
    }
}
