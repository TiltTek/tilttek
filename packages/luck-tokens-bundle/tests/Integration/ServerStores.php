<?php

declare(strict_types=1);

namespace Tilttek\LuckTokensBundle\Tests\Integration;

use Doctrine\DBAL\Connection;
use Override;
use Tilttek\LuckTokensBundle\Tests\Unit\Fixture\Interloper;

/**
 * What the store tests on a real server share: the connection through the interloper, opened with the tables empty
 * before each test, a second connection for the other writer, both closed after it. The server's URL is read from the
 * variable of the package, else from the one `phil-dev services env` sets; the test is skipped without either.
 */
trait ServerStores
{
    private ?Connection $server = null;

    private ?Connection $otherServer = null;

    private Interloper $serverInterloper;

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
        $this->serverInterloper = new Interloper();
        $this->server = Server::connect($url, $this->serverInterloper);
        $this->otherServer = Server::open($url);
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->server?->close();
        $this->otherServer?->close();
        $this->server = null;
        $this->otherServer = null;
    }

    #[Override]
    protected function connection(): Connection
    {
        return $this->server ?? self::fail('The server is connected in setUp().');
    }

    #[Override]
    protected function other(): Connection
    {
        return $this->otherServer ?? self::fail('The server is connected in setUp().');
    }

    #[Override]
    protected function interloper(): Interloper
    {
        return $this->serverInterloper;
    }
}
