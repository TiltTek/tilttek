<?php

declare(strict_types=1);

namespace Tilttek\Sets\Store;

use Override;
use Tilttek\Sets\Contract\DefinitionStore;
use Tilttek\Sets\SetDefinition;

/**
 * The versions of the sets in memory, for tests and for sets declared in the application's configuration.
 */
final class InMemoryDefinitionStore implements DefinitionStore
{
    /** @var array<string, array<int, SetDefinition>> */
    private array $definitions = [];

    #[Override]
    public function add(SetDefinition $definition): bool
    {
        if (isset($this->definitions[$definition->code][$definition->version])) {
            return false;
        }

        $this->definitions[$definition->code][$definition->version] = $definition;

        return true;
    }

    #[Override]
    public function latest(string $code): ?SetDefinition
    {
        $versions = $this->definitions[$code] ?? [];

        return [] === $versions ? null : $versions[max(array_keys($versions))];
    }

    #[Override]
    public function version(string $code, int $version): ?SetDefinition
    {
        return $this->definitions[$code][$version] ?? null;
    }
}
