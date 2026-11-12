<?php

declare(strict_types=1);

namespace Tilttek\Leaderboards\Tests\Unit\Store;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;
use Tilttek\Leaderboards\Store\Valkey\RawCommandConnection;

#[CoversClass(RawCommandConnection::class)]
final class RawCommandConnectionTest extends TestCase
{
    public function testAClientThatSendsARawCommand(): void
    {
        $client = new class {
            /**
             * @var list<array<string>>
             */
            public array $sent = [];

            public function rawCommand(string ...$arguments): mixed
            {
                $this->sent[] = $arguments;

                return match ($arguments[0]) {
                    'HGET' => false,
                    'PING' => true,
                    'ZRANGE' => ['k' => 'a', 'l' => ['b', 3, false]],
                    'ZCARD' => 4,
                    default => 1.5,
                };
            }
        };
        $connection = new RawCommandConnection($client);

        self::assertNull($connection->execute('HGET', 'h', 'f'));
        self::assertSame(1, $connection->execute('PING'));
        self::assertSame(['a', ['b', 3, null]], $connection->execute('ZRANGE', 'z', '0', '-1'));
        self::assertSame(4, $connection->execute('ZCARD', 'z'));
        self::assertNull($connection->execute('OTHER'));
        self::assertSame(['HGET', 'h', 'f'], $client->sent[0]);
    }

    public function testAClientThatExecutesARawCommand(): void
    {
        $client = new class {
            /**
             * @param list<string> $arguments
             */
            public function executeRaw(array $arguments): mixed
            {
                return implode(' ', $arguments);
            }
        };

        self::assertSame('ZCARD z', new RawCommandConnection($client)->execute('ZCARD', 'z'));
    }

    public function testAnotherObjectIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('A Valkey client sends a command by rawCommand() or executeRaw(); stdClass does neither.');

        new RawCommandConnection(new stdClass());
    }
}
