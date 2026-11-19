<?php

declare(strict_types=1);

namespace Tilttek\ExchangeBundle\Tests\Integration;

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
        return ['TILTTEK_EXCHANGE_TEST_POSTGRESQL_URL', 'PHIL_TEST_POSTGRESQL_URL'];
    }
}
