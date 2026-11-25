<?php

declare(strict_types=1);

namespace Tilttek\LuckTokensBundle\Tests\Unit\Infrastructure;

use Phil\PrivacyBundle\Domain\Retention\ErasureEffect;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;
use Tilttek\LuckTokens\Event\HolderErased;
use Tilttek\LuckTokens\LuckTokens;
use Tilttek\LuckTokens\Store\InMemoryTokenLedger;
use Tilttek\LuckTokens\Store\InMemoryTokenRules;
use Tilttek\LuckTokens\TableRules;
use Tilttek\LuckTokensBundle\Application\LockedLuckTokens;
use Tilttek\LuckTokensBundle\Infrastructure\Doctrine\DbalTokenRules;
use Tilttek\LuckTokensBundle\Infrastructure\Privacy\LuckTokensPrivacy;
use Tilttek\LuckTokensBundle\Tests\Unit\Fixture\Database;
use Tilttek\LuckTokensBundle\Tests\Unit\Fixture\Recorder;
use Tilttek\LuckTokensBundle\Tests\Unit\Fixture\Table;

#[CoversClass(LuckTokensPrivacy::class)]
final class LuckTokensPrivacyTest extends TestCase
{
    public function testAMemberIsErasedFromTheLedgersUnderTheKindConfigured(): void
    {
        [$tokens, $ledger, $events] = $this->tokens();
        $tokens->grant(Table::ref('table:1'), 'fate', Table::ref('user:44'), 2, Table::gm(), 'op-1');
        $tokens->grant(Table::ref('table:1'), 'fate', Table::ref('member:44'), 1, Table::gm(), 'op-2');

        $privacy = new LuckTokensPrivacy($tokens, 'user');

        $report = $privacy->erase(DataSubject::fromString('44'));

        self::assertSame('tilttek_luck_tokens', $privacy->module()->value);
        self::assertSame('tilttek_luck_tokens', $report->module->value);
        self::assertSame([
            'tilttek_luck_tokens_entry.holder' => ErasureEffect::Pseudonymised,
            'tilttek_luck_tokens_entry.actor' => ErasureEffect::Pseudonymised,
            'tilttek_luck_tokens_entry.reason' => ErasureEffect::Deleted,
        ], $report->effects);
        self::assertSame('erased', $ledger->entries(Table::ref('table:1'))[0]->holder->key);
        self::assertSame('member:44', $ledger->entries(Table::ref('table:1'))[1]->holder->toString());
        self::assertInstanceOf(HolderErased::class, $events->events[2]);
        self::assertEquals($report, $privacy->erase(DataSubject::fromString('44')), 'An erasure sent again reports the same.');
        self::assertCount(3, $events->events);
    }

    public function testWithTheLocksTheMemberIsErasedUnderTheirLock(): void
    {
        $store = new InMemoryStore();
        $heldDuring = null;
        [$tokens, $ledger] = $this->tokens(new Recorder(static function (object $event) use ($store, &$heldDuring): void {
            if ($event instanceof HolderErased) {
                $heldDuring = !new LockFactory($store)->createLock(LockedLuckTokens::PREFIX.'.holder.member:44')->acquire();
            }
        }));
        $tokens->grant(Table::ref('table:1'), 'fate', Table::ref('member:44'), 2, Table::gm(), 'op-1');
        $privacy = new LuckTokensPrivacy($tokens, 'member', new LockedLuckTokens($tokens, new LockFactory($store)));

        $privacy->erase(DataSubject::fromString('44'));

        self::assertTrue($heldDuring, "The member's lock is held while they are erased.");
        self::assertSame('erased', $ledger->entries(Table::ref('table:1'))[0]->holder->key);
        self::assertTrue(new LockFactory($store)->createLock(LockedLuckTokens::PREFIX.'.holder.member:44')->acquire(), 'And released after.');
    }

    public function testWithTheRulesInTheDatabaseTheMemberLeavesTheGameMasters(): void
    {
        [$tokens] = $this->tokens();
        $rules = new DbalTokenRules(Database::connection());
        $rules->save(new TableRules(Table::ref('table:1'), Table::rules()->kinds(), [Table::ref('member:44'), Table::gm()]));

        $report = new LuckTokensPrivacy($tokens, 'member', null, $rules)->erase(DataSubject::fromString('44'));

        self::assertSame(ErasureEffect::Deleted, $report->effects['tilttek_luck_tokens_rules.game_masters'] ?? null);
        self::assertCount(4, $report->effects);
        self::assertEquals([Table::gm()], $rules->rulesFor(Table::ref('table:1'))?->gameMasters);
    }

    /**
     * @return array{LuckTokens, InMemoryTokenLedger, Recorder}
     */
    private function tokens(Recorder $events = new Recorder()): array
    {
        $rules = new InMemoryTokenRules();
        $rules->save(Table::rules());

        $ledger = new InMemoryTokenLedger();

        return [new LuckTokens($rules, $ledger, $events), $ledger, $events];
    }
}
