<?php

declare(strict_types=1);

namespace Tilttek\ActivityBundle\Tests\Unit\Fixture;

use Doctrine\DBAL\Connection;
use Override;

/**
 * A connection where another process inserts the same row just before this one: the next insertion runs twice, and
 * this process gets the second answer.
 */
final class RacingConnection extends Connection
{
    public bool $race = false;

    #[Override]
    public function executeStatement(string $sql, array $params = [], array $types = []): int|string
    {
        if ($this->race && str_starts_with($sql, 'INSERT')) {
            $this->race = false;
            parent::executeStatement($sql, $params, $types);
        }

        return parent::executeStatement($sql, $params, $types);
    }
}
