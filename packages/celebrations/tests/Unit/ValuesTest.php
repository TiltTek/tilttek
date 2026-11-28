<?php

declare(strict_types=1);

namespace Tilttek\Celebrations\Tests\Unit;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\Celebrations\Achievement;
use Tilttek\Celebrations\Celebration;
use Tilttek\Celebrations\Entry;
use Tilttek\Celebrations\EntryState;
use Tilttek\Celebrations\Exception\CelebrationException;
use Tilttek\Celebrations\Exception\InvalidCelebration;
use Tilttek\Celebrations\Group;
use Tilttek\Celebrations\Limits;
use Tilttek\Celebrations\Motion;
use Tilttek\Celebrations\Preferences;
use Tilttek\Celebrations\Text;

#[CoversClass(Achievement::class)]
#[CoversClass(Entry::class)]
#[CoversClass(Group::class)]
#[CoversClass(Limits::class)]
#[CoversClass(Preferences::class)]
#[CoversClass(Celebration::class)]
#[CoversClass(Text::class)]
#[CoversClass(InvalidCelebration::class)]
final class ValuesTest extends TestCase
{
    private ResourceReference $member;

    private DateTimeImmutable $at;

    protected function setUp(): void
    {
        $this->member = ResourceReference::of('member', '1');
        $this->at = new DateTimeImmutable('2026-11-27 10:00:00 UTC');
    }

    public function testAnAchievementKeepsWhatItWasGivenWithItsParametersSortedByName(): void
    {
        $until = $this->at->modify('+1 day');
        $achievement = new Achievement($this->member, 'progression', 'level', 'track.reader', 'reader#5', $this->at, ['to' => 5, 'from' => 4, 'track' => 'Reader'], $until);

        self::assertSame($this->member, $achievement->member);
        self::assertSame('progression', $achievement->source);
        self::assertSame('level', $achievement->kind);
        self::assertSame('track.reader', $achievement->subject);
        self::assertSame('reader#5', $achievement->occurrence);
        self::assertSame($this->at, $achievement->earnedAt);
        self::assertSame(['from' => 4, 'to' => 5, 'track' => 'Reader'], $achievement->parameters);
        self::assertSame($until, $achievement->showUntil);
    }

    public function testAnAchievementTakesKeysAtTheirBounds(): void
    {
        $key = str_repeat('a', 96);
        $occurrence = str_repeat('~', 191);
        $parameters = [];

        for ($p = 1; $p <= Achievement::MAX_PARAMETERS; ++$p) {
            $parameters['p'.$p] = str_repeat('é', Achievement::MAX_PARAMETER_LENGTH);
        }

        $achievement = new Achievement($this->member, $key, 'a.b-c_d', 'Badge..A', $occurrence, $this->at, $parameters);

        self::assertSame($key, $achievement->source);
        self::assertSame($occurrence, $achievement->occurrence);
        self::assertSame('Badge..A', $achievement->subject);
        self::assertCount(10, $achievement->parameters);
        self::assertNull($achievement->showUntil);
    }

    /**
     * @return iterable<string, array{string, string, string, string, string}>
     */
    public static function invalidKeys(): iterable
    {
        yield 'an empty source' => ['', 'badge', 'b', 'o', 'The key of a source must be 1 to 96 lowercase letters, digits, "_", "-" or single dots between them; "" is not.'];
        yield 'an uppercase kind' => ['badges', 'Badge', 'b', 'o', 'The key of a kind must be 1 to 96 lowercase letters, digits, "_", "-" or single dots between them; "Badge" is not.'];
        yield 'a source too long' => [str_repeat('a', 97), 'badge', 'b', 'o', \sprintf('The key of a source must be 1 to 96 lowercase letters, digits, "_", "-" or single dots between them; "%s" is not.', str_repeat('a', 97))];
        yield 'a kind with a double dot' => ['badges', 'a..b', 'b', 'o', 'The key of a kind must be 1 to 96 lowercase letters, digits, "_", "-" or single dots between them; "a..b" is not.'];
        yield 'a kind with a leading dot' => ['badges', '.a', 'b', 'o', 'The key of a kind must be 1 to 96 lowercase letters, digits, "_", "-" or single dots between them; ".a" is not.'];
        yield 'a kind with a trailing line break' => ['badges', "a\n", 'b', 'o', "The key of a kind must be 1 to 96 lowercase letters, digits, \"_\", \"-\" or single dots between them; \"a\n\" is not."];
        yield 'an empty subject' => ['badges', 'badge', '', 'o', 'The subject of an achievement is 1 to 191 visible ASCII characters, without spaces.'];
        yield 'a subject with a space' => ['badges', 'badge', 'a b', 'o', 'The subject of an achievement is 1 to 191 visible ASCII characters, without spaces.'];
        yield 'an empty occurrence' => ['badges', 'badge', 'b', '', 'The occurrence of an achievement is 1 to 191 visible ASCII characters, without spaces.'];
        yield 'an occurrence with a space' => ['badges', 'badge', 'b', 'a b', 'The occurrence of an achievement is 1 to 191 visible ASCII characters, without spaces.'];
        yield 'an occurrence too long' => ['badges', 'badge', 'b', str_repeat('a', 192), 'The occurrence of an achievement is 1 to 191 visible ASCII characters, without spaces.'];
        yield 'an occurrence with a trailing line break' => ['badges', 'badge', 'b', "a\n", 'The occurrence of an achievement is 1 to 191 visible ASCII characters, without spaces.'];
        yield 'an occurrence with a non-ASCII letter' => ['badges', 'badge', 'b', 'é', 'The occurrence of an achievement is 1 to 191 visible ASCII characters, without spaces.'];
    }

    #[DataProvider('invalidKeys')]
    public function testAnAchievementRefusesAnInvalidKey(string $source, string $kind, string $subject, string $occurrence, string $message): void
    {
        $this->expectException(InvalidCelebration::class);
        $this->expectExceptionMessageIsOrContains($message);

        new Achievement($this->member, $source, $kind, $subject, $occurrence, $this->at);
    }

    public function testAnAchievementRefusesTooManyParameters(): void
    {
        $parameters = [];

        for ($p = 0; $p <= Achievement::MAX_PARAMETERS; ++$p) {
            $parameters['p'.$p] = $p;
        }

        $this->expectException(InvalidCelebration::class);
        $this->expectExceptionMessageIsOrContains('An achievement carries at most 10 parameters; this one has 11.');

        new Achievement($this->member, 'badges', 'badge', 'b', 'o', $this->at, $parameters);
    }

    /**
     * @return iterable<string, array{array<string, int|string>, string}>
     */
    public static function invalidParameters(): iterable
    {
        yield 'a name that is not a key' => [['Level' => 1], 'The key of a parameter must be'];
        yield 'an empty text' => [['name' => ''], 'The parameter "name" is 1 to 100 characters of UTF-8 without control characters nor surrounding spaces.'];
        yield 'a text too long' => [['name' => str_repeat('a', 101)], 'The parameter "name" is 1 to 100 characters'];
        yield 'a text with a control character' => [['name' => "a\tb"], 'The parameter "name" is 1 to 100 characters'];
        yield 'a text with a surrounding space' => [['name' => 'a '], 'The parameter "name" is 1 to 100 characters'];
        yield 'a text with a trailing line break' => [['name' => "a\n"], 'The parameter "name" is 1 to 100 characters'];
        yield 'a text that is not UTF-8' => [['name' => "\xFF"], 'The parameter "name" is 1 to 100 characters'];
    }

    /**
     * @param array<string, int|string> $parameters
     */
    #[DataProvider('invalidParameters')]
    public function testAnAchievementRefusesAnInvalidParameter(array $parameters, string $message): void
    {
        $this->expectException(InvalidCelebration::class);
        $this->expectExceptionMessageIsOrContains($message);

        new Achievement($this->member, 'badges', 'badge', 'b', 'o', $this->at, $parameters);
    }

    public function testAnAchievementStopsBeingShownOnlyAfterItWasEarned(): void
    {
        try {
            new Achievement($this->member, 'badges', 'badge', 'b', 'o', $this->at, [], $this->at);
            self::fail('The same moment was taken.');
        } catch (InvalidCelebration $invalidCelebration) {
            self::assertSame('The moment an achievement stops being shown comes after the moment it was earned.', $invalidCelebration->getMessage());
            self::assertInstanceOf(CelebrationException::class, $invalidCelebration);
        }

        $this->expectException(InvalidCelebration::class);

        new Achievement($this->member, 'badges', 'badge', 'b', 'o', $this->at, [], $this->at->modify('-1 second'));
    }

    public function testAnEntryIsPendingWithoutASettlingMomentOrSettledWithOne(): void
    {
        $achievement = $this->achievement();
        $pending = new Entry($achievement, 1, EntryState::Pending, $this->at);

        self::assertSame('member:1', $pending->member());
        self::assertNull($pending->settledAt);
        self::assertSame($this->at, $pending->receivedAt);

        $seen = $pending->settled(EntryState::Seen, $this->at->modify('+1 minute'));
        self::assertSame(EntryState::Seen, $seen->state);
        self::assertSame(1, $seen->sequence);
        self::assertSame($achievement, $seen->achievement);
        self::assertSame($this->at, $seen->receivedAt);
        self::assertEquals($this->at->modify('+1 minute'), $seen->settledAt);

        try {
            new Entry($achievement, 1, EntryState::Pending, $this->at, $this->at);
            self::fail('A pending entry took a settling moment.');
        } catch (InvalidCelebration $invalidCelebration) {
            self::assertSame('A pending entry has no settling moment, and a settled entry has one.', $invalidCelebration->getMessage());
        }

        $this->expectException(InvalidCelebration::class);
        $this->expectExceptionMessageIsOrContains('A pending entry has no settling moment, and a settled entry has one.');

        new Entry($achievement, 1, EntryState::Seen, $this->at);
    }

    public function testAnEntrySequenceStartsAtOne(): void
    {
        self::assertSame(1, new Entry($this->achievement(), 1, EntryState::Pending, $this->at)->sequence);

        $this->expectException(InvalidCelebration::class);
        $this->expectExceptionMessageIsOrContains('The sequence of an entry starts at 1; 0 is not one.');

        new Entry($this->achievement(), 0, EntryState::Pending, $this->at);
    }

    public function testAnEntryIsNeverSettledAsPending(): void
    {
        $this->expectException(InvalidCelebration::class);
        $this->expectExceptionMessageIsOrContains('An entry is settled as seen, withdrawn or lapsed, never as pending.');

        new Entry($this->achievement(), 1, EntryState::Pending, $this->at)->settled(EntryState::Pending, $this->at);
    }

    public function testAPendingEntryLapsesWhenItsMomentToBeShownComes(): void
    {
        $until = $this->at->modify('+1 hour');
        $entry = new Entry($this->achievement($until), 3, EntryState::Pending, $this->at);

        $before = $until->modify('-1 second');
        self::assertSame($entry, $entry->at($before));
        self::assertTrue($entry->isShowableAt($before));

        $lapsed = $entry->at($until);
        self::assertSame(EntryState::Lapsed, $lapsed->state);
        self::assertSame($until, $lapsed->settledAt);
        self::assertFalse($entry->isShowableAt($until));

        $endless = new Entry($this->achievement(), 4, EntryState::Pending, $this->at);
        self::assertSame($endless, $endless->at($this->at->modify('+10 years')));

        $seen = new Entry($this->achievement($until), 5, EntryState::Seen, $this->at, $this->at);
        self::assertSame($seen, $seen->at($until->modify('+1 day')));
        self::assertFalse($seen->isShowableAt($this->at));
    }

    public function testAGroupShowsItsFirstEntriesAndCountsTheRest(): void
    {
        $first = new Entry($this->achievement(), 1, EntryState::Pending, $this->at);
        $second = new Entry($this->achievement(), 2, EntryState::Pending, $this->at);
        $group = new Group('badges', 'badge', [$first, $second], 5);

        self::assertSame($first, $group->first());
        self::assertFalse($group->isSingle());
        self::assertSame(3, $group->hidden());
        self::assertSame([1, 2], $group->sequences());

        $single = new Group('badges', 'badge', [$first], 1);
        self::assertTrue($single->isSingle());
        self::assertSame(0, $single->hidden());
    }

    public function testAGroupShowsAtLeastOneEntry(): void
    {
        $this->expectException(InvalidCelebration::class);
        $this->expectExceptionMessageIsOrContains('A group shows at least one entry.');

        new Group('badges', 'badge', [], 0);
    }

    public function testAGroupCountsAtLeastTheEntriesItShows(): void
    {
        $entry = new Entry($this->achievement(), 1, EntryState::Pending, $this->at);

        $this->expectException(InvalidCelebration::class);
        $this->expectExceptionMessageIsOrContains('A group counts at least the 2 entries it shows; 1 is fewer.');

        new Group('badges', 'badge', [$entry, $entry], 1);
    }

    public function testAGroupHoldsOneSourceAndOneKind(): void
    {
        $entry = new Entry($this->achievement(), 7, EntryState::Pending, $this->at);

        try {
            new Group('quests', 'badge', [$entry], 1);
            self::fail('Another source was taken.');
        } catch (InvalidCelebration $invalidCelebration) {
            self::assertSame('A group holds the entries of one source and one kind; sequence 7 is not of "quests" and "badge".', $invalidCelebration->getMessage());
        }

        $this->expectException(InvalidCelebration::class);
        $this->expectExceptionMessageIsOrContains('sequence 7 is not of "badges" and "level"');

        new Group('badges', 'level', [$entry], 1);
    }

    public function testLimitsHaveDefaultsAndBounds(): void
    {
        $limits = new Limits();
        self::assertSame(200, $limits->pendingPerMember);
        self::assertSame(500, $limits->entriesPerMember);
        self::assertSame(10, $limits->shownPerGroup);

        $smallest = new Limits(1, 1, 1);
        self::assertSame(1, $smallest->pendingPerMember);

        $largest = new Limits(1000, 10_000, 50);
        self::assertSame(1000, $largest->pendingPerMember);
        self::assertSame(10_000, $largest->entriesPerMember);
        self::assertSame(50, $largest->shownPerGroup);
    }

    /**
     * @return iterable<string, array{int, int, int, string}>
     */
    public static function invalidLimits(): iterable
    {
        yield 'no pending entry' => [0, 500, 10, 'The limit of pending entries per member is 1 to 1000; it is 0.'];
        yield 'too many pending entries' => [1001, 5000, 10, 'The limit of pending entries per member is 1 to 1000; it is 1001.'];
        yield 'fewer entries than pending' => [200, 199, 10, 'The limit of entries per member is 200 to 10000; it is 199.'];
        yield 'too many entries' => [200, 10_001, 10, 'The limit of entries per member is 200 to 10000; it is 10001.'];
        yield 'no entry shown' => [200, 500, 0, 'The limit of entries shown per group is 1 to 50; it is 0.'];
        yield 'too many shown' => [200, 500, 51, 'The limit of entries shown per group is 1 to 50; it is 51.'];
    }

    #[DataProvider('invalidLimits')]
    public function testLimitsRefuseValuesOutOfBounds(int $pending, int $entries, int $shown, string $message): void
    {
        $this->expectException(InvalidCelebration::class);
        $this->expectExceptionMessageIsOrContains($message);

        new Limits($pending, $entries, $shown);
    }

    public function testPreferencesCelebrateWithFullMotionAndNoSoundByDefault(): void
    {
        $preferences = new Preferences();

        self::assertTrue($preferences->celebrate);
        self::assertSame(Motion::Full, $preferences->motion);
        self::assertFalse($preferences->sound);
    }

    public function testTheDeviceReducesMotionButNeverAddsAny(): void
    {
        self::assertSame(Motion::Full, new Preferences()->motionFor(false));
        self::assertSame(Motion::Reduced, new Preferences()->motionFor(true));
        self::assertSame(Motion::Reduced, new Preferences(motion: Motion::Reduced)->motionFor(false));
        self::assertSame(Motion::Reduced, new Preferences(motion: Motion::Reduced)->motionFor(true));
        self::assertSame(Motion::None, new Preferences(motion: Motion::None)->motionFor(false));
        self::assertSame(Motion::None, new Preferences(motion: Motion::None)->motionFor(true));
    }

    public function testACelebrationKeepsWhatItShows(): void
    {
        $group = new Group('badges', 'badge', [new Entry($this->achievement(), 1, EntryState::Pending, $this->at)], 1);
        $celebration = new Celebration($group, Motion::Reduced, true, 2);

        self::assertSame($group, $celebration->group);
        self::assertSame(Motion::Reduced, $celebration->motion);
        self::assertTrue($celebration->sound);
        self::assertSame(2, $celebration->remaining);
    }

    private function achievement(?DateTimeImmutable $showUntil = null, ?DateTimeImmutable $earnedAt = null): Achievement
    {
        return new Achievement($this->member, 'badges', 'badge', 'badge.first-roll', 'badge.first-roll#1', $earnedAt ?? $this->at, [], $showUntil);
    }
}
