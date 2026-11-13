<?php

declare(strict_types=1);

namespace Tilttek\GameEngineBundle\Tests\Fixture;

use Override;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\SharedLockInterface;
use Symfony\Component\Lock\Store\InMemoryStore;

/**
 * A lock factory in memory that remembers the time to live of the last lock it created.
 */
final class RecordingLockFactory extends LockFactory
{
    public ?float $ttl = null;

    /** @var list<SharedLockInterface> kept, so that none is released by being let go */
    public array $created = [];

    public function __construct()
    {
        parent::__construct(new InMemoryStore());
    }

    #[Override]
    public function createLock(string $resource, ?float $ttl = 300.0, bool $autoRelease = true): SharedLockInterface
    {
        $this->ttl = $ttl;

        return $this->created[] = parent::createLock($resource, $ttl, $autoRelease);
    }
}
