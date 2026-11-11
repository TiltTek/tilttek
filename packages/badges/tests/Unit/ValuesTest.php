<?php

declare(strict_types=1);

namespace Tilttek\Badges\Tests\Unit;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\Badges\Award;
use Tilttek\Badges\Badge;
use Tilttek\Badges\Criterion\Manual;
use Tilttek\Badges\Exception\InvalidBadge;
use Tilttek\Badges\Profile;
use Tilttek\Badges\Progress;
use Tilttek\Badges\Standing;
use Tilttek\Badges\Tier;

#[CoversClass(Tier::class)]
#[CoversClass(Progress::class)]
#[CoversClass(Badge::class)]
#[CoversClass(Award::class)]
#[CoversClass(Profile::class)]
#[CoversClass(Standing::class)]
final class ValuesTest extends TestCase
{
    public function testATier(): void
    {
        $tier = new Tier('commenter', 1);
        self::assertSame(['commenter', 1], [$tier->series, $tier->rank]);
        self::assertSame(64, \strlen(new Tier('a'.str_repeat('b', 63), 3)->series));
    }

    public function testATierSeriesIsNamed(): void
    {
        $this->expectExceptionObject(InvalidBadge::because('"Gold" is not a series name: 1 to 64 lower-case letters, digits, "_", "." or "-", starting with a letter.'));
        new Tier('Gold', 1);
    }

    public function testATierSeriesEndsWithTheName(): void
    {
        $this->expectException(InvalidBadge::class);
        new Tier("gold\n", 1);
    }

    public function testATierSeriesHasALimit(): void
    {
        $this->expectException(InvalidBadge::class);
        new Tier('a'.str_repeat('b', 64), 1);
    }

    public function testATierRankStartsAtOne(): void
    {
        $this->expectExceptionObject(InvalidBadge::because('The rank of a tier starts at 1; 0 is not one.'));
        new Tier('commenter', 0);
    }

    public function testAProgress(): void
    {
        $progress = new Progress(7, 10);
        self::assertFalse($progress->isReached());
        self::assertSame(7, $progress->shown());
        self::assertSame(0, $progress->times(true));
        self::assertSame(0, $progress->times(false));

        $reached = new Progress(10, 10);
        self::assertTrue($reached->isReached());
        self::assertSame(10, $reached->shown());
        self::assertSame(1, $reached->times(false));

        $passed = new Progress(23, 10);
        self::assertSame(10, $passed->shown());
        self::assertSame(2, $passed->times(true));
        self::assertSame(1, $passed->times(false));
        self::assertSame(0, new Progress(0, 1)->current);
    }

    public function testAProgressIsNeverNegative(): void
    {
        $this->expectExceptionObject(InvalidBadge::because('A progress is never negative; -1 is.'));
        new Progress(-1, 1);
    }

    public function testAProgressHasATarget(): void
    {
        $this->expectExceptionObject(InvalidBadge::because('The target of a progress is at least 1; 0 is not.'));
        new Progress(0, 0);
    }

    public function testABadge(): void
    {
        $badge = new Badge('commenter.gold', 'badges.commenter.gold', new Manual());
        self::assertSame('', $badge->description);
        self::assertNull($badge->image);
        self::assertFalse($badge->repeatable);
        self::assertFalse($badge->hidden);
        self::assertFalse($badge->revocable);
        self::assertNull($badge->tier);
        self::assertSame(255, mb_strlen(new Badge('a', str_repeat('é', 255), new Manual(), str_repeat('é', 1020))->name));
    }

    public function testABadgeCodeIsChecked(): void
    {
        $this->expectExceptionObject(InvalidBadge::because('"Gold" is not a badge code: 1 to 64 lower-case letters, digits, "_", "." or "-", starting with a letter.'));
        new Badge('Gold', 'Gold', new Manual());
    }

    public function testABadgeCodeEndsWithTheCode(): void
    {
        $this->expectException(InvalidBadge::class);
        new Badge("gold\n", 'Gold', new Manual());
    }

    public function testABadgeCodeHasALimit(): void
    {
        self::assertSame(64, \strlen(new Badge('a'.str_repeat('b', 63), 'Gold', new Manual())->code));
        $this->expectException(InvalidBadge::class);
        new Badge('a'.str_repeat('b', 64), 'Gold', new Manual());
    }

    public function testABadgeHasAName(): void
    {
        $this->expectExceptionObject(InvalidBadge::because('The name of the badge "gold" is 1 to 255 characters.'));
        new Badge('gold', '  ', new Manual());
    }

    public function testABadgeNameHasALimit(): void
    {
        $this->expectExceptionObject(InvalidBadge::because('The name of the badge "gold" is 1 to 255 characters.'));
        new Badge('gold', str_repeat('é', 256), new Manual());
    }

    public function testABadgeDescriptionHasALimit(): void
    {
        $this->expectExceptionObject(InvalidBadge::because('The description of the badge "gold" is at most 1020 characters.'));
        new Badge('gold', 'Gold', new Manual(), str_repeat('é', 1021));
    }

    public function testAnAward(): void
    {
        $award = new Award('gold', ResourceReference::of('member', '1'), 2, new DateTimeImmutable('2026-11-07 10:00:00 America/Toronto'), 'game:1', true);
        self::assertSame(['badge' => 'gold', 'member' => 'member:1', 'number' => 2, 'awarded_at' => '2026-11-07T10:00:00-05:00', 'cause' => 'game:1', 'manual' => true], $award->toArray());

        $plain = new Award('gold', ResourceReference::of('member', '1'), 1, new DateTimeImmutable('2026-11-07 10:00:00 UTC'), str_repeat('k', 255));
        self::assertFalse($plain->manual);
        self::assertNull(new Award('gold', ResourceReference::of('member', '1'), 1, new DateTimeImmutable())->cause);
    }

    public function testAnAwardNumberStartsAtOne(): void
    {
        $this->expectExceptionObject(InvalidBadge::because('The number of an award starts at 1; 0 is not one.'));
        new Award('gold', ResourceReference::of('member', '1'), 0, new DateTimeImmutable());
    }

    public function testAnAwardCauseIsNotEmpty(): void
    {
        $this->expectExceptionObject(InvalidBadge::because('The cause of an award is 1 to 255 bytes; this one is 0.'));
        new Award('gold', ResourceReference::of('member', '1'), 1, new DateTimeImmutable(), '');
    }

    public function testAnAwardCauseHasALimit(): void
    {
        $this->expectExceptionObject(InvalidBadge::because('The cause of an award is 1 to 255 bytes; this one is 256.'));
        new Award('gold', ResourceReference::of('member', '1'), 1, new DateTimeImmutable(), str_repeat('k', 256));
    }

    public function testAProfile(): void
    {
        $member = ResourceReference::of('member', '1');
        $profile = new Profile($member);
        self::assertTrue($profile->isEmpty());
        self::assertSame([[], [], null], [$profile->shown, $profile->featured, $profile->title]);

        $shown = $profile->show('gold')->show('silver')->show('gold');
        self::assertSame(['gold', 'silver'], $shown->shown);
        self::assertTrue($shown->shows('gold'));
        self::assertFalse($shown->shows('bronze'));
        self::assertFalse($shown->isEmpty());
        self::assertSame($member, $shown->member);

        $featured = $shown->feature(['bronze', 'gold']);
        self::assertSame(['gold', 'silver', 'bronze'], $featured->shown);
        self::assertSame(['bronze', 'gold'], $featured->featured);

        $titled = $featured->entitle('crown');
        self::assertSame(['gold', 'silver', 'bronze', 'crown'], $titled->shown);
        self::assertSame('crown', $titled->title);
        self::assertSame(['bronze', 'gold'], $titled->featured);
        self::assertSame($member, $titled->member);

        $hidden = $titled->hide('gold')->hide('crown');
        self::assertSame(['silver', 'bronze'], $hidden->shown);
        self::assertSame(['bronze'], $hidden->featured);
        self::assertNull($hidden->title);
        self::assertSame('silver', $titled->entitle('silver')->hide('crown')->title);

        $untitled = $titled->entitle(null);
        self::assertNull($untitled->title);
        self::assertSame($titled->shown, $untitled->shown);
        self::assertSame(['gold'], $titled->feature(['gold'])->featured);
        self::assertSame('crown', $titled->feature(['gold'])->title);
        self::assertSame($member, $titled->feature(['gold'])->member);
    }

    public function testAProfileListsABadgeOnce(): void
    {
        $this->expectExceptionObject(InvalidBadge::because('The profile of member:1 lists a badge twice.'));
        new Profile(ResourceReference::of('member', '1'), ['gold', 'gold']);
    }

    public function testAProfileFeaturesABadgeOnce(): void
    {
        $this->expectExceptionObject(InvalidBadge::because('The profile of member:1 lists a badge twice.'));
        new Profile(ResourceReference::of('member', '1'), ['gold'], ['gold', 'gold']);
    }

    public function testAProfileFeaturesOnlyWhatItShows(): void
    {
        $this->expectExceptionObject(InvalidBadge::because('The profile of member:1 features or wears a badge it does not show.'));
        new Profile(ResourceReference::of('member', '1'), ['gold'], ['silver']);
    }

    public function testAProfileWearsOnlyWhatItShows(): void
    {
        $this->expectExceptionObject(InvalidBadge::because('The profile of member:1 features or wears a badge it does not show.'));
        new Profile(ResourceReference::of('member', '1'), ['gold'], [], 'silver');
    }

    public function testAStanding(): void
    {
        $badge = new Badge('gold', 'Gold', new Manual());
        self::assertFalse(new Standing($badge, new Progress(0, 1), 0)->isEarned());
        self::assertTrue(new Standing($badge, new Progress(0, 1), 1)->isEarned());
    }
}
