<?php

declare(strict_types=1);

namespace Tilttek\TableSafety\Tests\Unit\Signal;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\TableSafety\Exception\InvalidAnswer;
use Tilttek\TableSafety\Signal\Audience;
use Tilttek\TableSafety\Signal\CheckInTally;
use Tilttek\TableSafety\Signal\Signal;
use Tilttek\TableSafety\Signal\SignalKind;

#[CoversClass(Signal::class)]
#[CoversClass(CheckInTally::class)]
final class SignalTest extends TestCase
{
    private const string ID = '0123456789abcdef0123456789abcdef';

    public function testAnAnonymousSignal(): void
    {
        $signal = new Signal(self::ID, ResourceReference::of('table', '7'), SignalKind::XCard, Audience::Table, new DateTimeImmutable('2026-11-12 20:00:00+00:00'));

        self::assertTrue($signal->isAnonymous());
        self::assertFalse($signal->isAcknowledged());
        self::assertSame([
            'id' => self::ID,
            'table' => 'table:7',
            'kind' => 'x-card',
            'audience' => 'table',
            'raised_at' => '2026-11-12T20:00:00+00:00',
            'raised_by' => null,
            'acknowledged_at' => null,
        ], $signal->toArray());
    }

    public function testANamedSignalIsAcknowledgedAndForgotten(): void
    {
        $signal = new Signal(self::ID, ResourceReference::of('table', '7'), SignalKind::OpenDoor, Audience::GameMaster, new DateTimeImmutable('2026-11-12 20:00:00+00:00'), ResourceReference::of('member', 'ana'));

        self::assertFalse($signal->isAnonymous());

        $seen = $signal->acknowledge(new DateTimeImmutable('2026-11-12 20:01:00+00:00'));
        self::assertTrue($seen->isAcknowledged());
        self::assertFalse($signal->isAcknowledged());
        self::assertSame('member:ana', $seen->toArray()['raised_by']);
        self::assertSame('2026-11-12T20:01:00+00:00', $seen->toArray()['acknowledged_at']);

        $forgotten = $seen->anonymous();
        self::assertTrue($forgotten->isAnonymous());
        self::assertTrue($forgotten->isAcknowledged());
        self::assertSame([...$seen->toArray(), 'raised_by' => null], $forgotten->toArray());
    }

    public function testANewIdentifierIsRandom(): void
    {
        $id = Signal::newId();

        self::assertMatchesRegularExpression('/^[0-9a-f]{32}$/D', $id);
        self::assertNotSame($id, Signal::newId());
    }

    public function testAnIdentifierHas32HexadecimalCharacters(): void
    {
        foreach (['', '0123456789abcdef0123456789abcde', '0123456789abcdef0123456789abcdeF', self::ID."\n", '0123456789abcdef0123456789abcdef0'] as $id) {
            try {
                new Signal($id, ResourceReference::of('table', '7'), SignalKind::XCard, Audience::Table, new DateTimeImmutable());
                self::fail(\sprintf('"%s" is refused.', $id));
            } catch (InvalidAnswer $invalidAnswer) {
                self::assertSame('A signal is identified by 32 hexadecimal characters.', $invalidAnswer->getMessage());
            }
        }
    }

    public function testTheAnswersToTheLastCheckInAreCounted(): void
    {
        $tally = CheckInTally::of([
            $this->signal(SignalKind::AllGood, '19:00'),
            $this->signal(SignalKind::CheckIn, '19:30'),
            $this->signal(SignalKind::NotOkay, '19:31'),
            $this->signal(SignalKind::CheckIn, '20:00'),
            $this->signal(SignalKind::AllGood, '20:01'),
            $this->signal(SignalKind::AllGood, '20:01'),
            $this->signal(SignalKind::XCard, '20:02'),
            $this->signal(SignalKind::Unsure, '20:03'),
        ]);

        self::assertEquals(new DateTimeImmutable('2026-11-12 20:00'), $tally->askedAt);
        self::assertSame(2, $tally->allGood);
        self::assertSame(1, $tally->unsure);
        self::assertSame(0, $tally->notOkay);
        self::assertSame(3, $tally->answers());
        self::assertTrue($tally->needsAttention());
    }

    public function testNothingIsCountedWithoutACheckIn(): void
    {
        $tally = CheckInTally::of([$this->signal(SignalKind::AllGood, '19:00'), $this->signal(SignalKind::NotOkay, '19:01')]);

        self::assertNull($tally->askedAt);
        self::assertSame(0, $tally->answers());
        self::assertFalse($tally->needsAttention());
    }

    public function testAllGoodNeedsNoAttention(): void
    {
        $tally = CheckInTally::of([$this->signal(SignalKind::CheckIn, '19:00'), $this->signal(SignalKind::AllGood, '19:01')]);

        self::assertSame(1, $tally->answers());
        self::assertFalse($tally->needsAttention());
        self::assertTrue(CheckInTally::of([$this->signal(SignalKind::CheckIn, '19:00'), $this->signal(SignalKind::NotOkay, '19:01')])->needsAttention());
        self::assertSame(1, CheckInTally::of([$this->signal(SignalKind::CheckIn, '19:00'), $this->signal(SignalKind::NotOkay, '19:01')])->notOkay);
    }

    private function signal(SignalKind $kind, string $at): Signal
    {
        return new Signal(Signal::newId(), ResourceReference::of('table', '7'), $kind, Audience::Table, new DateTimeImmutable('2026-11-12 '.$at));
    }
}
