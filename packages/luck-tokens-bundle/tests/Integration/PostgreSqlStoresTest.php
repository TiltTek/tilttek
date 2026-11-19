<?php

declare(strict_types=1);

namespace Tilttek\LuckTokensBundle\Tests\Integration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Tilttek\LuckTokensBundle\Infrastructure\Doctrine\DbalTokenLedger;
use Tilttek\LuckTokensBundle\Infrastructure\Doctrine\DbalTokenRules;
use Tilttek\LuckTokensBundle\Infrastructure\Doctrine\LuckTokensTables;
use Tilttek\LuckTokensBundle\Infrastructure\Doctrine\Rows;
use Tilttek\LuckTokensBundle\Tests\Unit\Infrastructure\StoresTestCase;

/**
 * The DBAL stores on a real PostgreSQL server; the other writer has a connection of its own.
 */
#[CoversClass(DbalTokenLedger::class)]
#[CoversClass(DbalTokenRules::class)]
#[CoversClass(LuckTokensTables::class)]
#[CoversClass(Rows::class)]
#[Group('postgresql')]
final class PostgreSqlStoresTest extends StoresTestCase
{
    use OnPostgreSql;
    use ServerStores;
}
