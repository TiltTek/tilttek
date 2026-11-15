<?php

declare(strict_types=1);

namespace Tilttek\Leaderboards\Tests\Unit\Fixture;

use Override;
use Tilttek\Leaderboards\Contract\ValkeyConnection;

/**
 * A Valkey connection that answers each command with the reply given for its name, and writes down every command it
 * is sent: what a store sends, and how it reads replies a real server would not give.
 */
final class ScriptedConnection implements ValkeyConnection
{
    /**
     * @var list<list<string>>
     */
    public array $sent = [];

    /**
     * @param array<string, int|list<mixed>|string|null> $replies by command name
     */
    public function __construct(
        private readonly array $replies = [],
    ) {
    }

    #[Override]
    public function execute(string ...$arguments): array|int|string|null
    {
        $this->sent[] = array_values($arguments);

        return $this->replies[$arguments[0] ?? ''] ?? null;
    }
}
