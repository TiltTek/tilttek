<?php

declare(strict_types=1);

namespace Tilttek\Leaderboards\Store\Valkey;

use InvalidArgumentException;
use Override;
use Tilttek\Leaderboards\Contract\ValkeyConnection;

/**
 * A Valkey connection through a client the application already has: the `Redis` or `RedisCluster` object of PHP's
 * `redis` extension, a Relay client — each sends a command by `rawCommand()` —, or a Predis client, by `executeRaw()`.
 * A missing reply, which the extension gives as false, is returned as null.
 */
final readonly class RawCommandConnection implements ValkeyConnection
{
    public function __construct(
        private object $client,
    ) {
        if (!method_exists($client, 'rawCommand') && !method_exists($client, 'executeRaw')) {
            throw new InvalidArgumentException(\sprintf('A Valkey client sends a command by rawCommand() or executeRaw(); %s does neither.', $client::class));
        }
    }

    #[Override]
    public function execute(string ...$arguments): array|int|string|null
    {
        $client = $this->client;

        if (method_exists($client, 'rawCommand')) {
            return self::normalize($client->rawCommand(...$arguments));
        }

        return method_exists($client, 'executeRaw') ? self::normalize($client->executeRaw($arguments)) : null;
    }

    /**
     * @return int|list<mixed>|string|null
     */
    private static function normalize(mixed $reply): array|int|string|null
    {
        return match (true) {
            \is_array($reply) => array_map(self::normalize(...), array_values($reply)),
            \is_int($reply), \is_string($reply) => $reply,
            true === $reply => 1,
            default => null,
        };
    }
}
