<?php

declare(strict_types=1);

namespace Tilttek\GeneratorBundle\Infrastructure\Lock;

use Closure;
use Override;
use Symfony\Component\Lock\LockFactory;
use Tilttek\GeneratorBundle\Application\Contract\StateLock;

/**
 * The lock of a state on Symfony's lock factory: one lock by generator and key, named from a hash of both, waited for,
 * and released when the work ends. Its time to live bounds how long a process that died keeps the others waiting.
 */
final readonly class SymfonyStateLock implements StateLock
{
    /**
     * How long a lock is kept, in seconds, when the process that holds it does not release it.
     */
    public const float TTL = 30.0;

    public function __construct(private LockFactory $factory)
    {
    }

    #[Override]
    public function hold(string $generator, string $key, Closure $work): mixed
    {
        $lock = $this->factory->createLock(self::resource($generator, $key), self::TTL);
        $lock->acquire(true);

        try {
            return $work();
        } finally {
            $lock->release();
        }
    }

    /**
     * The name of the lock of a generator and a key: the key is any text, which a hash makes fit any lock store.
     */
    public static function resource(string $generator, string $key): string
    {
        return 'tilttek_generator.state.'.hash('sha256', $generator."\0".$key);
    }
}
