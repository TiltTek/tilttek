<?php

declare(strict_types=1);

namespace Tilttek\Progression\Tests\Unit\Streak;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\Activity\Activity;
use Tilttek\Progression\Exception\InvalidProgression;
use Tilttek\Progression\Exception\UnknownStreak;
use Tilttek\Progression\Streak\ContextTimezone;
use Tilttek\Progression\Streak\FixedTimezone;
use Tilttek\Progression\Streak\Grace;
use Tilttek\Progression\Streak\StreakRule;
use Tilttek\Progression\Streak\StreakRules;
use Tilttek\Progression\Streak\StreakUnit;

#[CoversClass(StreakRule::class)]
#[CoversClass(StreakRules::class)]
#[CoversClass(StreakUnit::class)]
#[CoversClass(Grace::class)]
#[CoversClass(FixedTimezone::class)]
#[CoversClass(ContextTimezone::class)]
#[CoversClass(UnknownStreak::class)]
final class StreakRulesTest extends TestCase
{
    public function testTheUnits(): void
    {
        $utc = new DateTimeZone('UTC');
        $toronto = new DateTimeZone('America/Toronto');

        self::assertSame(0, StreakUnit::Day->index(new DateTimeImmutable('1970-01-01 23:59:59 UTC'), $utc));
        self::assertSame(-1, StreakUnit::Day->index(new DateTimeImmutable('1969-12-31 12:00:00 UTC'), $utc));
        self::assertSame(20764, StreakUnit::Day->index(new DateTimeImmutable('2026-11-07 12:00:00 UTC'), $utc));
        self::assertSame(120530, StreakUnit::Day->index(new DateTimeImmutable('2300-01-01 12:00:00 UTC'), $utc));
        self::assertSame(20764, StreakUnit::Day->index(new DateTimeImmutable('2026-11-08 03:00:00 UTC'), $toronto));
        self::assertSame(20765, StreakUnit::Day->index(new DateTimeImmutable('2026-11-08 03:00:00 UTC'), $utc));
        self::assertSame(0, StreakUnit::Week->index(new DateTimeImmutable('1970-01-01 12:00:00 UTC'), $utc));
        self::assertSame(1, StreakUnit::Week->index(new DateTimeImmutable('1970-01-05 00:00:00 UTC'), $utc));
        self::assertSame(-1, StreakUnit::Week->index(new DateTimeImmutable('1969-12-28 12:00:00 UTC'), $utc));
        self::assertSame(2966, StreakUnit::Week->index(new DateTimeImmutable('2026-11-02 00:00:00 UTC'), $utc));
        self::assertSame(2966, StreakUnit::Week->index(new DateTimeImmutable('2026-11-08 23:59:59 UTC'), $utc));
        self::assertSame(2967, StreakUnit::Week->index(new DateTimeImmutable('2026-11-09 03:00:00 UTC'), $utc));
        self::assertSame(2966, StreakUnit::Week->index(new DateTimeImmutable('2026-11-09 03:00:00 UTC'), $toronto));
        self::assertSame('2026-11-07', StreakUnit::Day->label(20764));
        self::assertSame('1969-12-31', StreakUnit::Day->label(-1));
        self::assertSame('2026-W45', StreakUnit::Week->label(2966));
        self::assertSame('1970-W01', StreakUnit::Week->label(0));
        self::assertSame('3119-W49', StreakUnit::Week->label(60000));
        self::assertSame('2027-W01', StreakUnit::Week->label(StreakUnit::Week->index(new DateTimeImmutable('2027-01-04'), $utc)));
    }

    public function testAGrace(): void
    {
        self::assertSame(1, new Grace(1, 2)->allowed);
        self::assertSame(Grace::MAX_WINDOW, new Grace(3, Grace::MAX_WINDOW)->window);

        foreach ([[0, 7], [2, 2], [3, 2], [1, Grace::MAX_WINDOW + 1]] as [$allowed, $window]) {
            try {
                new Grace($allowed, $window);
                self::fail('The grace was expected to be refused.');
            } catch (InvalidProgression $invalidProgression) {
                self::assertSame(\sprintf('A grace forgives at least 1 missed unit in a window longer than that, of at most 366; %d in %d is not one.', $allowed, $window), $invalidProgression->getMessage());
            }
        }
    }

    public function testAStreak(): void
    {
        $rule = new StreakRule('daily-play', ['game.*'], context: ['ranked' => true]);

        self::assertSame(StreakUnit::Day, $rule->unit);
        self::assertNull($rule->grace);
        self::assertTrue($rule->matches(Activity::of('k', 'member:1', 'game.played', new DateTimeImmutable(), context: ['ranked' => true])));
        self::assertFalse($rule->matches(Activity::of('k', 'member:1', 'game.played', new DateTimeImmutable(), context: ['ranked' => false])));
        self::assertFalse($rule->matches(Activity::of('k', 'member:1', 'news.read', new DateTimeImmutable(), context: ['ranked' => true])));
    }

    public function testAStreakNameIsChecked(): void
    {
        $this->expectExceptionObject(InvalidProgression::name('streak', 'Daily'));
        new StreakRule('Daily', ['game.*']);
    }

    public function testAStreakReadsAVerb(): void
    {
        $this->expectExceptionObject(InvalidProgression::because('The streak "daily" reads at least one verb.'));
        new StreakRule('daily', []);
    }

    public function testStreaksFromAConfiguration(): void
    {
        $rules = StreakRules::fromArray([
            'weekly' => ['verbs' => ['game.played', 'news.read'], 'unit' => 'week', 'grace' => null],
            'daily' => ['verbs' => ['game.played'], 'grace' => ['allowed' => 1, 'window' => 7], 'context' => ['ranked' => true]],
        ], 'Europe/Paris');

        self::assertSame('Europe/Paris', $rules->timezone->getName());
        self::assertSame(['daily', 'weekly'], array_map(static fn (StreakRule $rule): string => $rule->name, $rules->all()));
        self::assertSame(['game.played', 'news.read'], $rules->patterns());
        self::assertSame(StreakUnit::Day, $rules->get('daily')->unit);
        self::assertSame(7, $rules->get('daily')->grace?->window);
        self::assertSame(1, $rules->get('daily')->grace->allowed);
        self::assertSame(['ranked' => true], $rules->get('daily')->conditions->values);
        self::assertSame(StreakUnit::Week, $rules->get('weekly')->unit);
        self::assertNull($rules->get('weekly')->grace);
        self::assertSame('UTC', StreakRules::fromArray([])->timezone->getName());
    }

    public function testAnUnknownStreak(): void
    {
        $this->expectExceptionObject(UnknownStreak::named('monthly'));
        StreakRules::fromArray([])->get('monthly');
    }

    public function testAStreakUnit(): void
    {
        $this->expectExceptionObject(InvalidProgression::because('"month" is not a streak unit: day or week.'));
        StreakRules::fromArray(['daily' => ['verbs' => ['a'], 'unit' => 'month']]);
    }

    public function testATimezone(): void
    {
        $this->expectExceptionObject(InvalidProgression::because('"Mars/Olympus" is not a timezone.'));
        StreakRules::fromArray([], 'Mars/Olympus');
    }

    public function testTwoStreaksHaveTwoNames(): void
    {
        $this->expectExceptionObject(InvalidProgression::because('Two streaks are named "daily".'));
        new StreakRules(new DateTimeZone('UTC'), new StreakRule('daily', ['a']), new StreakRule('daily', ['b']));
    }

    public function testTheTimezoneOfAnActivity(): void
    {
        $paris = new DateTimeZone('Europe/Paris');
        $resolver = new ContextTimezone('timezone', $paris);

        self::assertSame($paris, new FixedTimezone($paris)->timezoneFor(Activity::of('k', 'member:1', 'a', new DateTimeImmutable())));
        self::assertSame('America/Toronto', $resolver->timezoneFor(Activity::of('k', 'member:1', 'a', new DateTimeImmutable(), context: ['timezone' => 'America/Toronto']))->getName());
        self::assertSame($paris, $resolver->timezoneFor(Activity::of('k', 'member:1', 'a', new DateTimeImmutable())));
        self::assertSame($paris, $resolver->timezoneFor(Activity::of('k', 'member:1', 'a', new DateTimeImmutable(), context: ['timezone' => 5])));
        self::assertSame($paris, $resolver->timezoneFor(Activity::of('k', 'member:1', 'a', new DateTimeImmutable(), context: ['timezone' => 'Mars/Olympus'])));
        self::assertSame($paris, $resolver->timezoneFor(Activity::of('k', 'member:1', 'a', new DateTimeImmutable(), context: ['tz' => 'America/Toronto'])));
    }
}
