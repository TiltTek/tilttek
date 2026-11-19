<?php

declare(strict_types=1);

namespace Tilttek\LuckTokensBundle\Tests\Unit\Fixture;

use Closure;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Middleware;
use Doctrine\DBAL\Driver\Middleware\AbstractConnectionMiddleware;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;
use Doctrine\DBAL\Driver\Statement;
use Override;
use SensitiveParameter;
use Tilttek\LuckTokensBundle\Infrastructure\Doctrine\LuckTokensTables;

/**
 * Another writer that reaches the ledger between the moment a store reads its last position and the moment it inserts
 * its entries: armed with an operation, the middleware runs it once, just before the next insertion of an entry.
 */
final class Interloper implements Middleware
{
    /** @var list<Closure(): mixed> */
    private array $others = [];

    /**
     * @param Closure(): mixed $other
     */
    public function arm(Closure $other): void
    {
        $this->others[] = $other;
    }

    #[Override]
    public function wrap(Driver $driver): Driver
    {
        $interloper = $this;

        return new class($driver, $interloper) extends AbstractDriverMiddleware {
            public function __construct(Driver $driver, private readonly Interloper $interloper)
            {
                parent::__construct($driver);
            }

            #[Override]
            public function connect(#[SensitiveParameter] array $params): Connection
            {
                $interloper = $this->interloper;

                return new class(parent::connect($params), $interloper) extends AbstractConnectionMiddleware {
                    public function __construct(Connection $connection, private readonly Interloper $interloper)
                    {
                        parent::__construct($connection);
                    }

                    #[Override]
                    public function prepare(string $sql): Statement
                    {
                        if (str_starts_with($sql, 'INSERT INTO '.LuckTokensTables::ENTRIES)) {
                            $this->interloper->interlope();
                        }

                        return parent::prepare($sql);
                    }
                };
            }
        };
    }

    /**
     * Runs the next armed operation, if any.
     */
    public function interlope(): void
    {
        $other = array_shift($this->others);

        if (null !== $other) {
            $other();
        }
    }
}
