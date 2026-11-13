<?php

declare(strict_types=1);

namespace Tilttek\GameEngineBundle\Tests\Fixture;

use Closure;
use Doctrine\DBAL\Connection;
use Override;

/**
 * A connection that records every read of rows with its parameters, runs a change of the database just before a read,
 * and lets a test rewrite the rows a read gives — as another process or another driver would.
 */
final class ProbeConnection extends Connection
{
    /** @var list<array{string, array<mixed>}> */
    public array $queries = [];

    /** @var ?Closure(string): void runs before every read, with its SQL */
    public ?Closure $before = null;

    /** @var ?Closure(string, array<string, mixed>): array<string, mixed> gives the row a read returns in place of the one it found */
    public ?Closure $rewrite = null;

    /**
     * The parameters of the reads whose SQL starts with a text.
     *
     * @return list<array<mixed>>
     */
    public function reads(string $start): array
    {
        $found = [];

        foreach ($this->queries as [$sql, $params]) {
            if (str_starts_with($sql, $start)) {
                $found[] = $params;
            }
        }

        return $found;
    }

    #[Override]
    public function fetchAssociative(string $query, array $params = [], array $types = []): array|false
    {
        $this->note($query, $params);
        $row = parent::fetchAssociative($query, $params, $types);

        return \is_array($row) ? $this->rewritten($query, $row) : $row;
    }

    #[Override]
    public function fetchAllAssociative(string $query, array $params = [], array $types = []): array
    {
        $this->note($query, $params);

        return array_map(fn (array $row): array => $this->rewritten($query, $row), parent::fetchAllAssociative($query, $params, $types));
    }

    /**
     * @param array<mixed> $params
     */
    private function note(string $query, array $params): void
    {
        $this->queries[] = [$query, $params];

        if ($this->before instanceof Closure) {
            ($this->before)($query);
        }
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function rewritten(string $query, array $row): array
    {
        return $this->rewrite instanceof Closure ? ($this->rewrite)($query, $row) : $row;
    }
}
