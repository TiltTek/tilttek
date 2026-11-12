<?php

declare(strict_types=1);

namespace Tilttek\GeneratorBundle\Application;

use Closure;
use Tilttek\Generator\Draw;
use Tilttek\Generator\DrawResult;
use Tilttek\Generator\Exception\GeneratorException;
use Tilttek\Generator\Exception\InvalidDefinition;
use Tilttek\Generator\Generator;
use Tilttek\GeneratorBundle\Application\Contract\StateLock;
use Tilttek\GeneratorBundle\Application\Contract\StateStore;
use Tilttek\GeneratorBundle\Application\Exception\InvalidStateKey;

/**
 * The tables with a memory of the application: a draw goes on from the state the last draw of the same generator and
 * key left — the key names a campaign, a table, a person — and keeps the state it leaves for the next one. The
 * application reads, sets or forgets a state between draws: a new heading, a chaos factor raised by a scene.
 *
 * Two draws on the same generator and key at the same moment each start from the same state, and the last one kept
 * wins, unless a lock is given: the second draw then waits for the first and goes on from the state it left. Setting and
 * forgetting a state take the same lock.
 */
final readonly class MemoryTables
{
    /**
     * The longest key, in characters.
     */
    public const int KEY_LENGTH = 200;

    public function __construct(private Generator $generator, private StateStore $store, private ?StateLock $lock = null)
    {
    }

    /**
     * Draws from the generator, from the state kept under the key, and keeps the state the draw leaves. A generator
     * without a state draws as usual and keeps nothing.
     *
     * @throws InvalidStateKey
     * @throws GeneratorException
     */
    public function draw(string $generator, string $key, int $count = 1, bool $unique = false, ?string $seed = null): DrawResult
    {
        return $this->locked($generator, $key, function () use ($generator, $key, $count, $unique, $seed): DrawResult {
            $result = $this->generator->draw($generator, new Draw($count, $unique, $seed, $this->store->load($generator, $key)));

            if (null !== $result->state) {
                $this->store->save($generator, $key, $result->state);
            }

            return $result;
        });
    }

    /**
     * The state kept under the key, or null when no draw has left one.
     *
     * @return array<string, string>|null
     *
     * @throws InvalidStateKey
     */
    public function state(string $generator, string $key): ?array
    {
        return $this->store->load($generator, $this->key($key));
    }

    /**
     * Sets the state kept under the key, read as `Draw` reads a state: at most 32 names to texts of at most 1024
     * characters.
     *
     * @param array<array-key, mixed> $state
     *
     * @throws InvalidStateKey
     * @throws InvalidDefinition when the state is not a map of names to short texts
     */
    public function set(string $generator, string $key, array $state): void
    {
        $state = new Draw(state: $state)->state ?? [];

        $this->locked($generator, $key, fn () => $this->store->save($generator, $key, $state));
    }

    /**
     * Forgets the state kept under the key: the next draw starts again.
     *
     * @throws InvalidStateKey
     */
    public function forget(string $generator, string $key): void
    {
        $this->locked($generator, $key, fn () => $this->store->forget($generator, $key));
    }

    /**
     * Runs the work on a checked key, behind the lock when there is one.
     *
     * @template T
     *
     * @param-immediately-invoked-callable $work
     *
     * @param Closure(): T $work
     *
     * @return T
     *
     * @throws InvalidStateKey
     */
    private function locked(string $generator, string $key, Closure $work): mixed
    {
        $key = $this->key($key);

        return $this->lock instanceof StateLock ? $this->lock->hold($generator, $key, $work) : $work();
    }

    private function key(string $key): string
    {
        if ('' === $key || mb_strlen($key) > self::KEY_LENGTH) {
            throw InvalidStateKey::of($key);
        }

        return $key;
    }
}
