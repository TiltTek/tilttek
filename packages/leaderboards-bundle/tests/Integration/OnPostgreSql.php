<?php

declare(strict_types=1);

namespace Tilttek\LeaderboardsBundle\Tests\Integration;

/**
 * The PostgreSQL server of `phil-dev services`, or of the package's own variable.
 */
trait OnPostgreSql
{
    /**
     * @return list<string>
     */
    protected static function variables(): array
    {
        return ['TILTTEK_LEADERBOARDS_TEST_POSTGRESQL_URL', 'PHIL_TEST_POSTGRESQL_URL'];
    }
}
