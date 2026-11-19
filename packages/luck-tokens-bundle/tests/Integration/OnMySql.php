<?php

declare(strict_types=1);

namespace Tilttek\LuckTokensBundle\Tests\Integration;

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
        return ['TILTTEK_LUCK_TOKENS_TEST_MYSQL_URL', 'PHIL_TEST_MYSQL_URL'];
    }
}
