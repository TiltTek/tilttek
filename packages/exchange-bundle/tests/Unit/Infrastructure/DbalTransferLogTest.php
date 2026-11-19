<?php

declare(strict_types=1);

namespace Tilttek\ExchangeBundle\Tests\Unit\Infrastructure;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use Tilttek\Exchange\Transfer\TransferLog;
use Tilttek\ExchangeBundle\Infrastructure\Doctrine\DbalTransferLog;

#[CoversClass(DbalTransferLog::class)]
final class DbalTransferLogTest extends TransferLogTestCase
{
    #[Override]
    protected function log(): TransferLog
    {
        return new DbalTransferLog(Database::connection());
    }
}
