<?php

declare(strict_types=1);

namespace Tilttek\LeaderboardsBundle\Tests\Integration;

use Doctrine\DBAL\Connection;
use Override;

/**
 * What the store tests on a real server share: the connection, opened with the tables empty before each test, and
 * closed after it. The server's URL is read from the variable of the package, else from the one `phil-dev services
 * env` sets; the test is skipped without either.
 */
trait ServerStores
{
    private ?Connection $server = null;

    /**
     * The variables of the server's URL, the package's own first.
     *
     * @return list<string>
     */
    abstract protected static function variables(): array;

    #[Override]
    protected function setUp(): void
    {
        $url = Server::url(...static::variables()) ?? self::markTestSkipped(\sprintf('Set %s, or run phil-dev services, to test against a real server.', implode(' or ', static::variables())));
        $this->server = Server::connect($url);
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->server?->close();
        $this->server = null;
    }

    protected function server(): Connection
    {
        return $this->server ?? self::fail('The server is connected in setUp().');
    }
}
