<?php

declare(strict_types=1);

namespace Tilttek\CheckInRewards\Tests\Unit;

use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\CheckInRewards\Exception\InvalidPresence;
use Tilttek\CheckInRewards\Presence;

#[CoversClass(Presence::class)]
#[CoversClass(InvalidPresence::class)]
final class PresenceTest extends TestCase
{
    public function testAPresenceHoldsWhatTheApplicationResolved(): void
    {
        $presence = new Presence(ResourceReference::of('member', '44'), ResourceReference::of('session', '7'), ResourceReference::of('e', str_repeat('x', 98)), str_repeat('p', 100), str_repeat('t', 100));

        self::assertSame('e:'.str_repeat('x', 98), $presence->event?->toString());
        self::assertSame('UTC', $presence->timezone->getName());
        self::assertSame(100, \strlen((string) $presence->place));
        self::assertSame(100, \strlen((string) $presence->track));
    }

    public function testAnEventMustFitInTheContext(): void
    {
        $this->expectExceptionObject(new InvalidPresence('The reference of an event is at most 100 bytes; "e:'.str_repeat('x', 99).'" is 101.'));
        new Presence(ResourceReference::of('member', '44'), ResourceReference::of('session', '7'), ResourceReference::of('e', str_repeat('x', 99)));
    }

    public function testAPlaceMustFitInTheContext(): void
    {
        $this->expectExceptionObject(new InvalidPresence('The place of a presence is 1 to 100 bytes; this one is 0.'));
        new Presence(ResourceReference::of('member', '44'), ResourceReference::of('session', '7'), place: '');
    }

    public function testATrackMustFitInTheContext(): void
    {
        $this->expectExceptionObject(new InvalidPresence('The track of a presence is 1 to 100 bytes; this one is 101.'));
        new Presence(ResourceReference::of('member', '44'), ResourceReference::of('session', '7'), track: str_repeat('t', 101));
    }

    public function testACodeFitsFromOneToAHundredBytes(): void
    {
        self::assertFalse(Presence::fits(''));
        self::assertTrue(Presence::fits('a'));
        self::assertTrue(Presence::fits(str_repeat('a', 100)));
        self::assertFalse(Presence::fits(str_repeat('a', 101)));
    }
}
