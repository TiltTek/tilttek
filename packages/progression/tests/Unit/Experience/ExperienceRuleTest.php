<?php

declare(strict_types=1);

namespace Tilttek\Progression\Tests\Unit\Experience;

use DateTimeImmutable;
use DateTimeZone;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\Activity\Activity;
use Tilttek\Progression\Curve;
use Tilttek\Progression\Exception\InvalidProgression;
use Tilttek\Progression\Experience\Cap;
use Tilttek\Progression\Experience\ExperienceRule;
use Tilttek\Progression\Experience\Gain;
use Tilttek\Progression\Experience\Period;
use Tilttek\Progression\Names;

#[CoversClass(ExperienceRule::class)]
#[CoversClass(Cap::class)]
#[CoversClass(Period::class)]
#[CoversClass(Gain::class)]
#[CoversClass(Names::class)]
final class ExperienceRuleTest extends TestCase
{
    public function testARule(): void
    {
        $rule = new ExperienceRule('runs', ['game.ran', 'game.led'], 50, new Cap(200, Period::Day), ['role' => 'gm']);
        $ran = Activity::of('k1', 'member:1', 'game.ran', new DateTimeImmutable('2026-11-07'), quantity: 3, context: ['role' => 'gm', 'system' => 'x']);

        self::assertSame('runs', $rule->name);
        self::assertSame(200, $rule->cap?->amount);
        self::assertTrue($rule->perQuantity);
        self::assertTrue($rule->matches($ran));
        self::assertSame(150, $rule->experienceFor($ran));
        self::assertTrue($rule->matches(Activity::of('k2', 'member:1', 'game.led', new DateTimeImmutable('2026-11-07'), context: ['role' => 'gm'])));
        self::assertFalse($rule->matches(Activity::of('k3', 'member:1', 'game.ran', new DateTimeImmutable('2026-11-07'), context: ['role' => 'player'])));
        self::assertFalse($rule->matches(Activity::of('k4', 'member:1', 'game.played', new DateTimeImmutable('2026-11-07'), context: ['role' => 'gm'])));
        self::assertSame(50, new ExperienceRule('once', ['game.ran'], 50, perQuantity: false)->experienceFor($ran));
        self::assertSame(1, new ExperienceRule('a', ['x'], 1)->experience);
        self::assertSame(ExperienceRule::MAX_EXPERIENCE, new ExperienceRule('a', ['x'], ExperienceRule::MAX_EXPERIENCE)->experience);
    }

    public function testARuleNameIsChecked(): void
    {
        $this->expectExceptionObject(InvalidProgression::because('"Runs" is not a rule name: 1 to 64 lower-case letters, digits, "_", "." or "-", starting with a letter.'));
        new ExperienceRule('Runs', ['game.ran'], 1);
    }

    public function testNames(): void
    {
        self::assertSame('a', Names::check('a', 'track'));
        self::assertSame('a'.str_repeat('b', 63), Names::check('a'.str_repeat('b', 63), 'track'));

        foreach (['', '1a', 'a'.str_repeat('b', 64), "a\n", 'a b'] as $name) {
            try {
                Names::check($name, 'track');
                self::fail(\sprintf('"%s" was expected to be refused.', $name));
            } catch (InvalidProgression $invalidProgression) {
                self::assertStringStartsWith(\sprintf('"%s" is not a track name', $name), $invalidProgression->getMessage());
            }
        }
    }

    public function testARuleReadsAVerb(): void
    {
        $this->expectExceptionObject(InvalidProgression::because('The rule "runs" reads at least one verb.'));
        new ExperienceRule('runs', [], 1);
    }

    public function testARuleGivesExperience(): void
    {
        $this->expectExceptionObject(InvalidProgression::because('A rule gives 1 to 1000000 experience; "runs" gives 0.'));
        new ExperienceRule('runs', ['game.ran'], 0);
    }

    public function testARuleGivesNotTooMuch(): void
    {
        $this->expectExceptionObject(InvalidProgression::because('A rule gives 1 to 1000000 experience; "runs" gives 1000001.'));
        new ExperienceRule('runs', ['game.ran'], ExperienceRule::MAX_EXPERIENCE + 1);
    }

    public function testACap(): void
    {
        self::assertSame(1, new Cap(1, Period::Ever)->amount);
        self::assertSame(Curve::MAX_EXPERIENCE, new Cap(Curve::MAX_EXPERIENCE, Period::Ever)->amount);

        foreach ([0, Curve::MAX_EXPERIENCE + 1] as $amount) {
            try {
                new Cap($amount, Period::Day);
                self::fail('The cap was expected to be refused.');
            } catch (InvalidProgression $invalidProgression) {
                self::assertSame(\sprintf('A cap is 1 to 1000000000000000 experience; this one is %d.', $amount), $invalidProgression->getMessage());
            }
        }
    }

    public function testThePeriods(): void
    {
        $toronto = new DateTimeZone('America/Toronto');
        $sunday = new DateTimeImmutable('2026-11-09 03:00:00 UTC');

        self::assertSame(['2026-11-08 00:00', '2026-11-09 00:00'], $this->format(Period::Day->bounds($sunday, $toronto)));
        self::assertSame(['2026-11-02 00:00', '2026-11-09 00:00'], $this->format(Period::Week->bounds($sunday, $toronto)));
        self::assertSame(['2026-10-01 00:00', '2026-11-01 00:00'], $this->format(Period::Month->bounds(new DateTimeImmutable('2026-11-01 03:00:00 UTC'), $toronto)));
        self::assertSame(['2026-01-01 00:00', '2027-01-01 00:00'], $this->format(Period::Year->bounds(new DateTimeImmutable('2027-01-01 03:00:00 UTC'), $toronto)));
        self::assertSame(['2026-11-09 00:00', '2026-11-16 00:00'], $this->format(Period::Week->bounds(new DateTimeImmutable('2026-11-09 12:00:00'), $toronto)));
        self::assertSame('America/Toronto', Period::Day->bounds($sunday, $toronto)[0]?->getTimezone()->getName());
        self::assertSame([null, null], Period::Ever->bounds($sunday, $toronto));
    }

    public function testAGain(): void
    {
        $gain = new Gain('player', 'games', ResourceReference::fromString('character:9'), ResourceReference::fromString('member:1'), 'k1', 20, new DateTimeImmutable('2026-11-07 10:00:00 UTC'), '2026-11');

        self::assertSame(['track' => 'player', 'rule' => 'games', 'subject' => 'character:9', 'member' => 'member:1', 'activity' => 'k1', 'amount' => 20, 'occurred_at' => '2026-11-07T10:00:00+00:00', 'season' => '2026-11'], $gain->toArray());
        self::assertSame('', new Gain('p', 'g', $gain->subject, $gain->member, 'k', 1, $gain->occurredAt)->season);
    }

    /**
     * @param array{?DateTimeImmutable, ?DateTimeImmutable} $bounds
     *
     * @return list<?string>
     */
    private function format(array $bounds): array
    {
        return array_map(static fn (?DateTimeImmutable $date): ?string => $date?->format('Y-m-d H:i'), $bounds);
    }
}
