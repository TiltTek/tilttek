<?php

declare(strict_types=1);

namespace Tilttek\ExchangeBundle\Tests\Integration;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Tilttek\Exchange\Transfer\TransferLog;
use Tilttek\ExchangeBundle\Infrastructure\Doctrine\DbalTransferLog;
use Tilttek\ExchangeBundle\Infrastructure\Doctrine\ExchangeTables;
use Tilttek\ExchangeBundle\Infrastructure\Doctrine\Rows;
use Tilttek\ExchangeBundle\Tests\Unit\Infrastructure\TransferLogTestCase;

/**
 * The contract of a journal of transfers on a real MySQL server.
 */
#[CoversClass(DbalTransferLog::class)]
#[CoversClass(ExchangeTables::class)]
#[CoversClass(Rows::class)]
#[Group('mysql')]
final class MySqlTransferLogTest extends TransferLogTestCase
{
    use OnMySql;
    use ServerStores;

    #[Override]
    protected function log(): TransferLog
    {
        return new DbalTransferLog($this->server());
    }
}
