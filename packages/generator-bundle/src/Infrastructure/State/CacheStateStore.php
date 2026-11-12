<?php

declare(strict_types=1);

namespace Tilttek\GeneratorBundle\Infrastructure\State;

use Override;
use Psr\Cache\CacheItemPoolInterface;
use Tilttek\GeneratorBundle\Application\Contract\StateStore;

/**
 * The states in a PSR-6 cache pool, without expiry, under a key hashed from the generator and the application's key,
 * which may then hold any character. The pool decides how long a state lives: a pool on Doctrine DBAL or on Valkey
 * keeps it across deployments, `cache.app` until the cache is cleared.
 */
final readonly class CacheStateStore implements StateStore
{
    public function __construct(private CacheItemPoolInterface $pool)
    {
    }

    #[Override]
    public function load(string $generator, string $key): ?array
    {
        $item = $this->pool->getItem($this->key($generator, $key));
        $state = $item->isHit() ? $item->get() : null;

        if (!\is_array($state)) {
            return null;
        }

        $texts = [];

        foreach ($state as $name => $value) {
            if (\is_string($name) && \is_string($value)) {
                $texts[$name] = $value;
            }
        }

        return $texts;
    }

    #[Override]
    public function save(string $generator, string $key, array $state): void
    {
        $this->pool->save($this->pool->getItem($this->key($generator, $key))->set($state));
    }

    #[Override]
    public function forget(string $generator, string $key): void
    {
        $this->pool->deleteItem($this->key($generator, $key));
    }

    private function key(string $generator, string $key): string
    {
        return 'tilttek_generator_state.'.hash('sha256', $generator."\n".$key);
    }
}
