<?php

declare(strict_types=1);

namespace Tilttek\Leaderboards\Contract;

/**
 * A connection to a Valkey server — or a Redis server that speaks the same commands —, for the scores kept in its
 * sorted sets: one command and its arguments sent, its reply returned as the server gave it — an integer, a string,
 * null for nothing, or a list of replies.
 *
 * `RawCommandConnection` wraps the client of PHP's `redis` extension, of Relay, or of Predis; an application with
 * another client writes its own, of one method.
 */
interface ValkeyConnection
{
    /**
     * @return int|list<mixed>|string|null
     */
    public function execute(string ...$arguments): array|int|string|null;
}
