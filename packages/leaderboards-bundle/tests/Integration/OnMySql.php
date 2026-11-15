<?php

declare(strict_types=1);

namespace Tilttek\LeaderboardsBundle\Tests\Integration;

/**
 * The MySQL server of `phil-dev services`, or of the package's own variable.
 */
trait OnMySql
{
    /**
     * @return list<string>
     */
    protected static function variables(): array
    {
        return ['TILTTEK_LEADERBOARDS_TEST_MYSQL_URL', 'PHIL_TEST_MYSQL_URL'];
    }
}
