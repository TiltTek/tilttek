<?php

declare(strict_types=1);

namespace Tilttek\PointsBundle\Tests\Integration\Fixture;

use Closure;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Middleware;
use Doctrine\DBAL\Driver\Middleware\AbstractConnectionMiddleware;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;
use Doctrine\DBAL\Driver\Statement;
use Override;
use SensitiveParameter;

/**
 * A middleware that runs a closure once, just before the connection prepares the first statement that starts with a
 * prefix: what another process does meanwhile, in the same test.
 */
final class Meanwhile implements Middleware
{
    private ?Closure $meanwhile;

    public function __construct(
        private readonly string $prefix,
        Closure $meanwhile,
    ) {
        $this->meanwhile = $meanwhile;
    }

    #[Override]
    public function wrap(Driver $driver): Driver
    {
        $middleware = $this;

        return new class($driver, $middleware) extends AbstractDriverMiddleware {
            public function __construct(Driver $driver, private readonly Meanwhile $middleware)
            {
                parent::__construct($driver);
            }

            #[Override]
            public function connect(#[SensitiveParameter] array $params): Connection
            {
                $middleware = $this->middleware;

                return new class(parent::connect($params), $middleware) extends AbstractConnectionMiddleware {
                    public function __construct(Connection $connection, private readonly Meanwhile $middleware)
                    {
                        parent::__construct($connection);
                    }

                    #[Override]
                    public function prepare(string $sql): Statement
                    {
                        $this->middleware->before($sql);

                        return parent::prepare($sql);
                    }
                };
            }
        };
    }

    public function before(string $sql): void
    {
        if ($this->meanwhile instanceof Closure && str_starts_with($sql, $this->prefix)) {
            $meanwhile = $this->meanwhile;
            $this->meanwhile = null;
            $meanwhile();
        }
    }

    public function ran(): bool
    {
        return !$this->meanwhile instanceof Closure;
    }
}
