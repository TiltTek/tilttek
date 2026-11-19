<?php

declare(strict_types=1);

namespace Tilttek\LuckTokensBundle\Application;

use Closure;
use Phil\Resource\ResourceReference;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\LockInterface;
use Tilttek\LuckTokens\Event\HolderErased;
use Tilttek\LuckTokens\LuckTokens;
use Tilttek\LuckTokens\Receipt;

/**
 * The operations of `LuckTokens`, each under a Symfony lock, so that the operations on one holder — or one table — run
 * one after the other rather than meet in the ledger. The ledger's own check stays: a lock that expires, or a writer
 * that takes none, still meets `LedgerConflict` and its retries. The locks are held for the operation alone and
 * released even when it fails.
 *
 * | Operation | Holder scope | Table scope |
 * |---|---|---|
 * | `grant()`, `spend()` | the holder | the table |
 * | `transfer()` | both holders, in the order of their references | the table |
 * | `startSession()` | the table | the table |
 * | `eraseHolder()` | the member | the member |
 *
 * The service keeps nothing between two calls: one instance serves every request of a worker.
 */
final readonly class LockedLuckTokens
{
    public const string PREFIX = 'tilttek_luck_tokens';

    public const float DEFAULT_TTL = 30.0;

    public function __construct(
        private LuckTokens $tokens,
        private LockFactory $locks,
        private LockScope $scope = LockScope::Holder,
        private float $ttl = self::DEFAULT_TTL,
    ) {
    }

    /**
     * @see LuckTokens::grant()
     */
    public function grant(ResourceReference $table, string $kind, ResourceReference $holder, int $amount, ResourceReference $actor, string $operation, ?string $reason = null): Receipt
    {
        return $this->locked($this->keys($table, $holder), fn (): Receipt => $this->tokens->grant($table, $kind, $holder, $amount, $actor, $operation, $reason));
    }

    /**
     * @see LuckTokens::spend()
     */
    public function spend(ResourceReference $table, string $kind, ResourceReference $holder, int $amount, ResourceReference $actor, string $operation, ?string $reason = null): Receipt
    {
        return $this->locked($this->keys($table, $holder), fn (): Receipt => $this->tokens->spend($table, $kind, $holder, $amount, $actor, $operation, $reason));
    }

    /**
     * @see LuckTokens::transfer()
     */
    public function transfer(ResourceReference $table, string $kind, ResourceReference $from, ResourceReference $to, int $amount, ResourceReference $actor, string $operation, ?string $reason = null): Receipt
    {
        return $this->locked($this->keys($table, $from, $to), fn (): Receipt => $this->tokens->transfer($table, $kind, $from, $to, $amount, $actor, $operation, $reason));
    }

    /**
     * @see LuckTokens::startSession()
     *
     * @param list<ResourceReference> $holders
     */
    public function startSession(ResourceReference $table, string $session, array $holders, ResourceReference $actor, string $operation): Receipt
    {
        return $this->locked([self::key('table', $table)], fn (): Receipt => $this->tokens->startSession($table, $session, $holders, $actor, $operation));
    }

    /**
     * @see LuckTokens::eraseHolder()
     */
    public function eraseHolder(ResourceReference $member): ?HolderErased
    {
        return $this->locked([self::key('holder', $member)], fn (): ?HolderErased => $this->tokens->eraseHolder($member));
    }

    /**
     * The keys an operation on these holders of the table locks, in one order whatever the order given, so that two
     * transfers between the same holders, one each way, never hold one lock each while waiting for the other.
     *
     * @return list<string>
     */
    private function keys(ResourceReference $table, ResourceReference ...$holders): array
    {
        if (LockScope::Table === $this->scope) {
            return [self::key('table', $table)];
        }

        $keys = array_unique(array_map(static fn (ResourceReference $holder): string => self::key('holder', $holder), $holders));
        sort($keys);

        return $keys;
    }

    private static function key(string $what, ResourceReference $reference): string
    {
        return self::PREFIX.'.'.$what.'.'.$reference->toString();
    }

    /**
     * @template T
     *
     * @param list<string> $keys
     * @param Closure(): T $operation
     *
     * @return T
     */
    private function locked(array $keys, Closure $operation): mixed
    {
        /** @var list<LockInterface> $held */
        $held = [];

        try {
            foreach ($keys as $key) {
                $lock = $this->locks->createLock($key, $this->ttl);
                $lock->acquire(true);
                $held[] = $lock;
            }

            return $operation();
        } finally {
            foreach (array_reverse($held) as $lock) {
                $lock->release();
            }
        }
    }
}
