<?php

declare(strict_types=1);

namespace Tilttek\DiceTower\Application;

use Phil\Resource\ResourceReference;

/**
 * The topic of each table's tower: a prefix, then the table's reference, encoded so that it stays one segment.
 */
final readonly class TowerTopics
{
    public const string PREFIX = 'urn:tilttek:dice-tower:';

    public function __construct(
        private string $prefix = self::PREFIX,
    ) {
    }

    public function topic(ResourceReference $table): string
    {
        return $this->prefix.rawurlencode($table->toString());
    }
}
