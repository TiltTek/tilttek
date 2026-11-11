<?php

declare(strict_types=1);

namespace Tilttek\CollectionsBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use DateTimeZone;
use Override;
use Phil\Almanac\Almanac;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\Collections\Exception\InvalidCollection;
use Tilttek\CollectionsBundle\Infrastructure\Almanac\AlmanacDateRule;

#[CoversClass(AlmanacDateRule::class)]
final class AlmanacDateRuleTest extends TestCase
{
    #[Override]
    protected function setUp(): void
    {
        if (!class_exists(Almanac::class)) {
            self::markTestSkipped('phil/almanac is not installed: the computed dates of the item of the day are an optional integration.');
        }
    }

    public function testAnItemComesBackOnTheDatesTheAlmanacComputes(): void
    {
        $egg = new AlmanacDateRule('easter', 'egg');
        $turkey = new AlmanacDateRule('fourth thursday of november', 'turkey', 4);
        $new = new AlmanacDateRule('12-31', 'confetti', 3);
        $day = static fn (string $day): DateTimeImmutable => new DateTimeImmutable($day, new DateTimeZone('America/Toronto'));

        self::assertSame('egg', $egg->itemOn($day('2027-03-28')));
        self::assertNull($egg->itemOn($day('2027-03-29')));
        self::assertNull($egg->itemOn($day('2027-03-27')));
        self::assertSame(['turkey', 'turkey', null, null], [$turkey->itemOn($day('2026-11-26')), $turkey->itemOn($day('2026-11-29')), $turkey->itemOn($day('2026-11-30')), $turkey->itemOn($day('2026-11-25'))]);
        self::assertSame(['confetti', 'confetti', 'confetti', null], [$new->itemOn($day('2026-12-31')), $new->itemOn($day('2027-01-01')), $new->itemOn($day('2027-01-02')), $new->itemOn($day('2027-01-03'))]);
        self::assertSame('egg', new AlmanacDateRule('easter', 'egg', 366)->itemOn($day('2028-03-27')));
        self::assertNull($egg->itemOn($day('1500-04-01')));
        self::assertSame(['moon', null], [new AlmanacDateRule('full moon', 'moon')->itemOn($day('2026-11-24')), new AlmanacDateRule('full moon', 'moon')->itemOn($day('2026-11-25'))]);
        self::assertSame(['dark', null], [new AlmanacDateRule('new moon', 'dark', 20)->itemOn($day('2026-01-05')), new AlmanacDateRule('new moon', 'dark', 20)->itemOn($day('2025-12-18'))]);
        self::assertSame('egg', $egg->item);
        self::assertSame(4, $turkey->days);

        foreach ([['easter', 'Egg', 1, '"Egg" is not a item code'], ['easter', 'egg', 0, 'An item of computed dates is put forward 1 to 366 days; "egg" is 0.'], ['easter', 'egg', 367, 'An item of computed dates is put forward 1 to 366 days; "egg" is 367.'], ['someday', 'egg', 1, '"someday" is not a rule the almanac reads.']] as [$rule, $item, $days, $message]) {
            try {
                new AlmanacDateRule($rule, $item, $days);
                self::fail($message);
            } catch (InvalidCollection $invalidCollection) {
                self::assertStringStartsWith($message, $invalidCollection->getMessage());
            }
        }
    }
}
