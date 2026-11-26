<?php

declare(strict_types=1);

namespace Tilttek\Kudos\Tests\Unit;

use DateInterval;
use DateTimeZone;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\Kudos\Exception\InvalidKudos;
use Tilttek\Kudos\Exception\KudosRefused;
use Tilttek\Kudos\Limits;
use Tilttek\Kudos\Preferences\AcceptAll;
use Tilttek\Kudos\Refusal;
use Tilttek\Kudos\TimeZone\SiteTimeZone;

#[CoversClass(Limits::class)]
#[CoversClass(KudosRefused::class)]
#[CoversClass(AcceptAll::class)]
#[CoversClass(SiteTimeZone::class)]
final class LimitsTest extends TestCase
{
    public function testTheDefaultsAreFivePerDayAndThreePerReceiverInSevenDays(): void
    {
        $limits = new Limits();

        self::assertSame(5, $limits->daily);
        self::assertSame(3, $limits->perReceiver);
        self::assertSame('7', $limits->period->format('%d'));
    }

    public function testTheBoundsAreAccepted(): void
    {
        $lowest = new Limits(1, 1, new DateInterval('PT1H'));
        $highest = new Limits(100, 100, new DateInterval('P366D'));

        self::assertSame([1, 1, 100, 100], [$lowest->daily, $lowest->perReceiver, $highest->daily, $highest->perReceiver]);
    }

    /**
     * @return iterable<string, array{int, int, string, string}>
     */
    public static function invalidLimits(): iterable
    {
        yield 'no daily thanks' => [0, 3, 'P7D', 'The daily cap is 1 to 100 thanks; it is 0.'];
        yield 'too many daily thanks' => [101, 3, 'P7D', 'The daily cap is 1 to 100 thanks; it is 101.'];
        yield 'no thanks per receiver' => [5, 0, 'P7D', 'The limit per receiver is 1 to 100 thanks; it is 0.'];
        yield 'too many thanks per receiver' => [5, 101, 'P7D', 'The limit per receiver is 1 to 100 thanks; it is 101.'];
        yield 'a period too short' => [5, 3, 'PT59M59S', 'The period of the limit per receiver lasts one hour to 366 days.'];
        yield 'a period too long' => [5, 3, 'P366DT1S', 'The period of the limit per receiver lasts one hour to 366 days.'];
    }

    #[DataProvider('invalidLimits')]
    public function testLimitsOutOfBoundsAreRefused(int $daily, int $perReceiver, string $period, string $error): void
    {
        $this->expectException(InvalidKudos::class);
        $this->expectExceptionMessageIsOrContains($error);

        new Limits($daily, $perReceiver, new DateInterval($period));
    }

    public function testEachRefusalSaysWhy(): void
    {
        $messages = [];

        foreach (Refusal::cases() as $refusal) {
            $refused = KudosRefused::because($refusal);
            self::assertSame($refusal, $refused->refusal);
            $messages[$refusal->value] = $refused->getMessage();
        }

        self::assertSame([
            'self' => 'A member cannot thank themselves.',
            'declined' => 'This member does not take thanks.',
            'daily_cap' => 'The giver has given all the thanks of their day.',
            'pair_limit' => 'The giver has thanked this member as often as the period allows.',
        ], $messages);
    }

    public function testTheDefaultsAcceptEveryoneAndCountDaysInUtc(): void
    {
        $member = ResourceReference::of('member', '1');

        self::assertTrue(new AcceptAll()->accepts($member, ResourceReference::of('member', '2')));
        self::assertSame('UTC', new SiteTimeZone()->of($member)->getName());
        self::assertSame('Europe/Paris', new SiteTimeZone(new DateTimeZone('Europe/Paris'))->of($member)->getName());
    }
}
