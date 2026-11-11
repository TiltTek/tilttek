<?php

declare(strict_types=1);

namespace Tilttek\Progression\Tests\Unit;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\Progression\Exception\InvalidProgression;
use Tilttek\Progression\Exception\ProgressionException;
use Tilttek\Progression\Exception\UnknownStreak;
use Tilttek\Progression\Exception\UnknownTrack;
use Tilttek\Progression\LevelChange;
use Tilttek\Progression\ReachedMilestone;

#[CoversClass(LevelChange::class)]
#[CoversClass(ReachedMilestone::class)]
#[CoversClass(InvalidProgression::class)]
#[CoversClass(UnknownTrack::class)]
#[CoversClass(UnknownStreak::class)]
final class ValuesTest extends TestCase
{
    public function testALevelChange(): void
    {
        $member = ResourceReference::fromString('member:1');
        $at = new DateTimeImmutable('2026-11-07 UTC');

        self::assertTrue(new LevelChange('player', $member, '', 1, 2, $at, 'k1')->isUp());
        self::assertFalse(new LevelChange('player', $member, '', 2, 1, $at, 'k1')->isUp());
        self::assertFalse(new LevelChange('player', $member, '', 2, 2, $at, 'k1')->isUp());
    }

    public function testAMilestoneReached(): void
    {
        $reached = new ReachedMilestone('character', 'level-5', ResourceReference::fromString('character:9'), ResourceReference::fromString('member:1'), '2026-q4', new DateTimeImmutable('2026-11-07 10:00 UTC'), 'k1');

        self::assertSame(['track' => 'character', 'milestone' => 'level-5', 'subject' => 'character:9', 'member' => 'member:1', 'season' => '2026-q4', 'reached_at' => '2026-11-07T10:00:00+00:00', 'activity' => 'k1'], $reached->toArray());
    }

    public function testTheExceptions(): void
    {
        self::assertInstanceOf(ProgressionException::class, InvalidProgression::because('x'));
        self::assertSame('No track is named "author".', UnknownTrack::named('author')->getMessage());
        self::assertSame('No streak is named "monthly".', UnknownStreak::named('monthly')->getMessage());
        self::assertInstanceOf(ProgressionException::class, UnknownTrack::named('a'));
        self::assertInstanceOf(ProgressionException::class, UnknownStreak::named('a'));
    }
}
