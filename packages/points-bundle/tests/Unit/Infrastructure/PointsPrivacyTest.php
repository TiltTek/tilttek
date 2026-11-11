<?php

declare(strict_types=1);

namespace Tilttek\PointsBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Override;
use Phil\PrivacyBundle\Application\Port\PersonalDataEraser;
use Phil\PrivacyBundle\Domain\Retention\ErasureEffect;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\Points\Account;
use Tilttek\Points\Ledger\InMemoryLedger;
use Tilttek\Points\MovementKey;
use Tilttek\Points\MovementKind;
use Tilttek\PointsBundle\Infrastructure\Privacy\PointsPrivacy;

#[CoversClass(PointsPrivacy::class)]
final class PointsPrivacyTest extends TestCase
{
    #[Override]
    protected function setUp(): void
    {
        if (!interface_exists(PersonalDataEraser::class)) {
            self::markTestSkipped('phil/privacy-bundle is not installed: the export and erasure of a member are an optional integration.');
        }
    }

    public function testAMembersMovementsAreExportedInBatches(): void
    {
        $ledger = $this->ledger();
        $privacy = new PointsPrivacy($ledger, 'member', 2);

        self::assertSame('tilttek_points', $privacy->module()->value);
        self::assertSame([], $privacy->export(DataSubject::fromString('9')));

        $export = $privacy->export(DataSubject::fromString('1'));
        self::assertSame([1, 2, 4, 5], $this->positions($export));
        self::assertSame([
            'position' => 5,
            'key' => 'e',
            'kind' => 'transferred',
            'currency' => 'points',
            'debit' => 'member:member:2',
            'credit' => 'member:member:1',
            'amount' => 1,
            'occurred_at' => '2026-11-01T10:00:00+00:00',
            'written_at' => '2026-11-07T12:00:00+00:00',
            'cause' => null,
            'reverses' => null,
        ], ($export['points'] ?? [])[3] ?? null);

        self::assertSame([1, 2, 4, 5], $this->positions(new PointsPrivacy($ledger, 'member', 4)->export(DataSubject::fromString('1'))));
        self::assertSame([1, 2, 4, 5], $this->positions(new PointsPrivacy($ledger, 'member', 500)->export(DataSubject::fromString('1'))));
        self::assertSame([6], $this->positions(new PointsPrivacy($ledger, 'user', 500)->export(DataSubject::fromString('1'))));
    }

    public function testAMembersPointsAreErased(): void
    {
        $ledger = $this->ledger();
        $report = new PointsPrivacy($ledger, 'member', 500)->erase(DataSubject::fromString('1'));

        self::assertSame('tilttek_points', $report->module->value);
        self::assertSame(['tilttek_points_movement' => ErasureEffect::Deleted, 'tilttek_points_movement.debit' => ErasureEffect::Redacted, 'tilttek_points_movement.credit' => ErasureEffect::Redacted], $report->effects);
        self::assertNull($ledger->find(MovementKey::fromString('a')));
        self::assertSame('erased:member', $ledger->find(MovementKey::fromString('e'))?->movement->credit->toString());
        self::assertNotNull($ledger->find(MovementKey::fromString('f')));
    }

    private function ledger(): InMemoryLedger
    {
        $ledger = new InMemoryLedger();
        $at = new DateTimeImmutable('2026-11-07 12:00:00 UTC');
        $ledger->append(LedgerTestCase::movement('a', MovementKind::Granted, 'issuance:manual', 'member:member:1', 10), $at);
        $ledger->append(LedgerTestCase::movement('b', MovementKind::Spent, 'member:member:1', 'redemption:reward:1', 2), $at);
        $ledger->append(LedgerTestCase::movement('c', MovementKind::Granted, 'issuance:manual', 'member:member:2', 10), $at);
        $ledger->append(LedgerTestCase::movement('d', MovementKind::Granted, 'issuance:manual', 'member:member:1', 1, currency: 'stars'), $at);
        $ledger->append(LedgerTestCase::movement('e', MovementKind::Transferred, 'member:member:2', 'member:member:1', 1), $at);
        $ledger->append(LedgerTestCase::movement('f', MovementKind::Granted, 'issuance:manual', 'member:user:1', 1), $at);
        self::assertSame(9, $ledger->balance(Account::fromString('member:member:1'), 'points'));

        return $ledger;
    }

    /**
     * @param array<string, list<array<string, mixed>>> $export
     *
     * @return list<mixed>
     */
    private function positions(array $export): array
    {
        return array_map(static fn (array $movement): mixed => $movement['position'], $export['points'] ?? []);
    }
}
