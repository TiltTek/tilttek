<?php

declare(strict_types=1);

namespace Tilttek\LuckTokensBundle\Tests\Integration;

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
        return ['TILTTEK_LUCK_TOKENS_TEST_POSTGRESQL_URL', 'PHIL_TEST_POSTGRESQL_URL'];
    }
}
