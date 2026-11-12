<?php

declare(strict_types=1);

namespace Tilttek\GeneratorBundle\Infrastructure\State;

use Override;
use Tilttek\GeneratorBundle\Application\Contract\StateStore;

/**
 * The states in memory, for tests and demonstrations: they last as long as the process, and a long-running process
 * forgets them between requests, the service being reset with the kernel.
 */
final class InMemoryStateStore implements StateStore
{
    /**
     * @var array<string, array<string, array<string, string>>> by generator and key
     */
    private array $states = [];

    #[Override]
    public function load(string $generator, string $key): ?array
    {
        return $this->states[$generator][$key] ?? null;
    }

    #[Override]
    public function save(string $generator, string $key, array $state): void
    {
        $this->states[$generator][$key] = $state;
    }

    #[Override]
    public function forget(string $generator, string $key): void
    {
        unset($this->states[$generator][$key]);
    }

    /**
     * Forgets every state.
     */
    public function reset(): void
    {
        $this->states = [];
    }
}
