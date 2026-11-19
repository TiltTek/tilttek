<?php

declare(strict_types=1);

namespace Tilttek\ExchangeBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\Connection;
use Override;
use Tilttek\Exchange\Transaction;

/**
 * Runs the steps of an exchange in one transaction of the connection: the offer closed, the points moved and the
 * items changed hands all commit together, or roll back together. The ledger of `tilttek/points` and the application's
 * inventory take part when they are kept on the same connection.
 */
final readonly class DbalTransaction implements Transaction
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    #[Override]
    public function run(callable $steps): mixed
    {
        return $this->connection->transactional(static fn (): mixed => $steps());
    }
}
