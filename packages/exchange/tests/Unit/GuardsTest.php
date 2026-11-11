<?php

declare(strict_types=1);

namespace Tilttek\Exchange\Tests\Unit;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\Exchange\Allowance;
use Tilttek\Exchange\Exception\ExchangeRefused;
use Tilttek\Exchange\Exception\InvalidExchange;
use Tilttek\Exchange\Guards;
use Tilttek\Exchange\NoMemberSince;
use Tilttek\Exchange\Refusal;
use Tilttek\Exchange\Tests\Unit\Fixture\JoiningDates;
use Tilttek\Points\Rule\Period;

#[CoversClass(Guards::class)]
#[CoversClass(Allowance::class)]
#[CoversClass(NoMemberSince::class)]
#[CoversClass(ExchangeRefused::class)]
final class GuardsTest extends TestCase
{
    public function testGuardsAreOffByDefaultExceptTheSelfExchange(): void
    {
        $guards = new Guards();

        self::assertNull($guards->allowance);
        self::assertNull($guards->newMemberDelay);
        self::assertSame('UTC', $guards->timezone->getName());
        self::assertSame([null, null], $guards->window(new DateTimeImmutable('2026-11-07 12:00:00 UTC')));

        $guards->checkParties(self::member(1), self::member(2), new DateTimeImmutable());
        $guards->checkAllowance(self::member(1), 1_000_000, 1_000_000);

        $refused = $this->refusal(static fn () => $guards->checkParties(self::member(1), self::member(1), new DateTimeImmutable()));
        self::assertSame(Refusal::SelfExchange, $refused->refusal);
        self::assertSame('"member:1" cannot exchange with themselves.', $refused->getMessage());
    }

    public function testANewAccountWaitsForTheDelay(): void
    {
        $guards = new Guards(newMemberDelay: 'P7D', memberSince: new JoiningDates([
            'member:1' => '2026-10-01 00:00:00 UTC',
            'member:2' => '2026-10-31 12:00:00 UTC',
            'member:3' => '2026-10-31 12:00:01 UTC',
        ]));
        $now = new DateTimeImmutable('2026-11-07 12:00:00 UTC');

        self::assertSame('P7D', $guards->newMemberDelay?->format('P%dD'));
        $guards->checkParties(self::member(1), self::member(2), $now);
        $guards->checkParties(self::member(2), self::member(1), $now);

        $giver = $this->refusal(static fn () => $guards->checkParties(self::member(3), self::member(1), $now));
        self::assertSame(Refusal::NewMember, $giver->refusal);
        self::assertSame('"member:3" has not been a member long enough to exchange with others.', $giver->getMessage());

        $receiver = $this->refusal(static fn () => $guards->checkParties(self::member(1), self::member(3), $now));
        self::assertSame('"member:3" has not been a member long enough to exchange with others.', $receiver->getMessage());

        $unknown = $this->refusal(static fn () => $guards->checkParties(self::member(1), self::member(4), $now));
        self::assertSame('"member:4" has not been a member long enough to exchange with others.', $unknown->getMessage());

        $none = new Guards(newMemberDelay: 'P1D');
        self::assertSame(Refusal::NewMember, $this->refusal(static fn () => $none->checkParties(self::member(1), self::member(2), $now))->refusal);
        self::assertNull(new NoMemberSince()->joinedAt(self::member(1)));
    }

    public function testTheAllowanceCountsOverItsPeriodInTheTimezone(): void
    {
        $guards = new Guards(new Allowance(100, Period::Week), timezone: 'America/Toronto');
        self::assertSame('America/Toronto', $guards->timezone->getName());

        [$since, $until] = $guards->window(new DateTimeImmutable('2026-11-09 03:00:00 UTC'));
        self::assertSame('2026-11-02 00:00:00 -05:00', $since?->format('Y-m-d H:i:s P'));
        self::assertSame('2026-11-09 00:00:00 -05:00', $until?->format('Y-m-d H:i:s P'));
        self::assertSame([null, null], new Guards(new Allowance(1, Period::Ever))->window(new DateTimeImmutable()));

        $guards->checkAllowance(self::member(1), 60, 40);
        $refused = $this->refusal(static fn () => $guards->checkAllowance(self::member(1), 60, 41));
        self::assertSame(Refusal::AllowanceReached, $refused->refusal);
        self::assertSame('"member:1" may give 100 per week; 60 given already, 41 more is too many.', $refused->getMessage());
    }

    public function testAnAllowanceAllowsSomething(): void
    {
        self::assertSame(1, new Allowance(1, Period::Day)->limit);

        $this->expectException(InvalidExchange::class);
        $this->expectExceptionMessageIsOrContains('An allowance allows at least 1; 0 is not an allowance.');

        new Allowance(0, Period::Day);
    }

    public function testADelayIsADuration(): void
    {
        $this->expectException(InvalidExchange::class);
        $this->expectExceptionMessageIsOrContains('"seven days" is not an ISO 8601 duration like "P7D".');

        new Guards(newMemberDelay: 'seven days');
    }

    public function testATimezoneIsKnown(): void
    {
        $this->expectException(InvalidExchange::class);
        $this->expectExceptionMessageIsOrContains('"Mars/Olympus" is not a timezone.');

        new Guards(timezone: 'Mars/Olympus');
    }

    private static function member(int $id): ResourceReference
    {
        return ResourceReference::of('member', (string) $id);
    }

    private function refusal(callable $check): ExchangeRefused
    {
        try {
            $check();
        } catch (ExchangeRefused $exchangeRefused) {
            return $exchangeRefused;
        }

        self::fail('A refusal was expected.');
    }
}
