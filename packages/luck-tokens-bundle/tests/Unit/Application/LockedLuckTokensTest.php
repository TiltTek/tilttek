<?php

declare(strict_types=1);

namespace Tilttek\LuckTokensBundle\Tests\Unit\Application;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\SharedLockInterface;
use Tilttek\LuckTokens\Exception\TokenRefused;
use Tilttek\LuckTokens\LuckTokens;
use Tilttek\LuckTokens\Store\InMemoryTokenLedger;
use Tilttek\LuckTokens\Store\InMemoryTokenRules;
use Tilttek\LuckTokensBundle\Application\LockedLuckTokens;
use Tilttek\LuckTokensBundle\Application\LockScope;
use Tilttek\LuckTokensBundle\Tests\Unit\Fixture\Recorder;
use Tilttek\LuckTokensBundle\Tests\Unit\Fixture\RecordingStore;
use Tilttek\LuckTokensBundle\Tests\Unit\Fixture\Table;

#[CoversClass(LockedLuckTokens::class)]
#[CoversClass(LockScope::class)]
final class LockedLuckTokensTest extends TestCase
{
    private const string ANA = 'tilttek_luck_tokens.holder.member:ana';

    private const string BO = 'tilttek_luck_tokens.holder.member:bo';

    private const string TABLE = 'tilttek_luck_tokens.table.table:1';

    public function testEachOperationRunsUnderTheLocksOfItsHolders(): void
    {
        [$locked, $store, $held] = $this->tokens(LockScope::Holder);
        $table = Table::ref('table:1');
        $ana = Table::ref('member:ana');
        $bo = Table::ref('member:bo');

        self::assertSame(2, $locked->grant($table, 'fate', $ana, 2, Table::gm(), 'op-1', 'A clever plan')->balance());
        self::assertSame([self::ANA], $held->keys);
        self::assertSame(['save '.self::ANA, 'ttl '.self::ANA.' 30.0', 'delete '.self::ANA], $store->calls);

        $store->calls = [];
        self::assertSame(1, $locked->spend($table, 'fate', $ana, 1, $ana, 'op-2', 'Reroll')->balance());
        self::assertSame([self::ANA], $held->keys);
        self::assertSame('Reroll', $locked->spend($table, 'fate', $ana, 1, $ana, 'op-2', 'Reroll')->entries[0]->reason);

        $locked->grant($table, 'bennies', $bo, 2, Table::gm(), 'op-3');
        $store->calls = [];
        self::assertSame(1, $locked->transfer($table, 'bennies', $bo, $ana, 1, $bo, 'op-4', 'For the brave')->balance());
        self::assertSame([self::ANA, self::BO], $held->keys);
        self::assertSame(['save '.self::ANA, 'ttl '.self::ANA.' 30.0', 'save '.self::BO, 'ttl '.self::BO.' 30.0', 'delete '.self::BO, 'delete '.self::ANA], $store->calls, 'Locked in the order of the references, released the other way.');
        self::assertSame('For the brave', $locked->transfer($table, 'bennies', $bo, $ana, 1, $bo, 'op-4', 'For the brave')->entries[1]->reason);

        self::assertCount(7, $locked->startSession($table, 's-1', [Table::ref('member:cy')], Table::gm(), 'op-5')->entries);
        self::assertSame([self::TABLE], $held->keys);

        self::assertNotNull($locked->eraseHolder($bo));
        self::assertSame([self::BO], $held->keys);
    }

    public function testATableScopeLocksTheWholeTable(): void
    {
        [$locked, , $held] = $this->tokens(LockScope::Table, 5.0);
        $table = Table::ref('table:1');
        $ana = Table::ref('member:ana');
        $bo = Table::ref('member:bo');

        $locked->grant($table, 'bennies', $ana, 2, Table::gm(), 'op-1');
        self::assertSame([self::TABLE], $held->keys);
        $locked->spend($table, 'bennies', $ana, 1, $ana, 'op-2');
        self::assertSame([self::TABLE], $held->keys);
        $locked->transfer($table, 'bennies', $ana, $bo, 1, $ana, 'op-3');
        self::assertSame([self::TABLE], $held->keys);
        $locked->eraseHolder($ana);
        self::assertSame([self::ANA], $held->keys, 'An erasure spans every table: it takes the lock of the member.');
    }

    public function testTheLocksAreReleasedWhenTheOperationIsRefused(): void
    {
        [$locked, $store] = $this->tokens(LockScope::Holder, 5.0);
        $table = Table::ref('table:1');
        $ana = Table::ref('member:ana');

        try {
            $locked->transfer($table, 'bennies', $ana, $ana, 1, $ana, 'op-1');
            self::fail('Nobody passes tokens to themselves.');
        } catch (TokenRefused) {
            self::assertSame(['save '.self::ANA, 'ttl '.self::ANA.' 5.0', 'delete '.self::ANA], $store->calls, 'One lock for one holder.');
        }

        self::assertTrue(new LockFactory($store)->createLock(self::ANA)->acquire());
    }

    public function testAnOperationWaitsForTheLockAnotherWriterHolds(): void
    {
        [$locked, $store] = $this->tokens(LockScope::Holder, 5.0);
        $store->conflicts = 1;

        self::assertSame(2, $locked->grant(Table::ref('table:1'), 'fate', Table::ref('member:ana'), 2, Table::gm(), 'op-1')->balance());
        self::assertSame(['save '.self::ANA, 'save '.self::ANA, 'ttl '.self::ANA.' 5.0', 'delete '.self::ANA], $store->calls, 'The first save met the lock of the other writer; the operation waited and saved again.');
    }

    public function testTheLocksLiveThirtySecondsByDefault(): void
    {
        self::assertSame(30.0, LockedLuckTokens::DEFAULT_TTL);
        self::assertSame('tilttek_luck_tokens', LockedLuckTokens::PREFIX);
        self::assertSame(['holder', 'table'], array_map(static fn (LockScope $scope): string => $scope->value, LockScope::cases()));
    }

    /**
     * @return array{LockedLuckTokens, RecordingStore, object{keys: list<string>}}
     */
    private function tokens(LockScope $scope, ?float $ttl = null): array
    {
        $store = new RecordingStore();
        $held = new class {
            /** @var list<string> */
            public array $keys = [];
        };
        $events = new Recorder(static function () use ($store, $held): void {
            $held->keys = [];

            foreach ([self::ANA, self::BO, self::TABLE] as $key) {
                $probe = new LockFactory($store->store)->createLock($key);

                if ($probe->acquire()) {
                    $probe->release();
                } else {
                    $held->keys[] = $key;
                }
            }
        });
        $rules = new InMemoryTokenRules();
        $rules->save(Table::rules());

        $tokens = new LuckTokens($rules, new InMemoryTokenLedger(), $events);
        // It keeps the locks it made: a lock nobody released is not released either by the garbage collector.
        $factory = new class($store) extends LockFactory {
            /** @var list<SharedLockInterface> */
            public array $made = [];

            #[Override]
            public function createLock(string $resource, ?float $ttl = 300.0, bool $autoRelease = true): SharedLockInterface
            {
                return $this->made[] = parent::createLock($resource, $ttl, $autoRelease);
            }
        };

        return [null === $ttl ? new LockedLuckTokens($tokens, $factory, $scope) : new LockedLuckTokens($tokens, $factory, $scope, $ttl), $store, $held];
    }
}
