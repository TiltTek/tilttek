<?php

declare(strict_types=1);

namespace Tilttek\LuckTokensBundle\Tests\Unit\Fixture;

use Override;
use Symfony\Component\Lock\Exception\LockConflictedException;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\PersistingStoreInterface;
use Symfony\Component\Lock\Store\InMemoryStore;

/**
 * A lock store in memory that writes down what it is asked, in order: `save`, `ttl` and `delete`, with the key. The
 * store it writes to is public, for a probe that asks without being written down.
 */
final class RecordingStore implements PersistingStoreInterface
{
    /** @var list<string> */
    public array $calls = [];

    /**
     * How many of the next saves meet a lock held by another: each is written down, then refused.
     */
    public int $conflicts = 0;

    public function __construct(
        public readonly InMemoryStore $store = new InMemoryStore(),
    ) {
    }

    #[Override]
    public function save(Key $key): void
    {
        $this->calls[] = 'save '.$key;

        if (0 < $this->conflicts) {
            --$this->conflicts;

            throw new LockConflictedException();
        }

        $this->store->save($key);
    }

    #[Override]
    public function delete(Key $key): void
    {
        $this->calls[] = 'delete '.$key;
        $this->store->delete($key);
    }

    #[Override]
    public function exists(Key $key): bool
    {
        return $this->store->exists($key);
    }

    #[Override]
    public function putOffExpiration(Key $key, float $ttl): void
    {
        $this->calls[] = \sprintf('ttl %s %.1f', $key, $ttl);
        $this->store->putOffExpiration($key, $ttl);
    }
}
