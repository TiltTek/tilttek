<?php

declare(strict_types=1);

namespace Tilttek\Sets\Tests\Unit;

use DateTimeImmutable;
use Override;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\Sets\Completion;
use Tilttek\Sets\Event\SetCompleted;
use Tilttek\Sets\Event\TierReached;
use Tilttek\Sets\Exception\DefinitionConflict;
use Tilttek\Sets\Exception\InvalidSet;
use Tilttek\Sets\Exception\UnknownSet;
use Tilttek\Sets\Holdings;
use Tilttek\Sets\Progress;
use Tilttek\Sets\Reward;
use Tilttek\Sets\SetDefinition;
use Tilttek\Sets\Sets;
use Tilttek\Sets\Store\InMemoryCompletionStore;
use Tilttek\Sets\Store\InMemoryDefinitionStore;
use Tilttek\Sets\Tests\Unit\Fixture\FrozenClock;
use Tilttek\Sets\Tests\Unit\Fixture\RecordingDispatcher;
use Tilttek\Sets\Tier;

#[CoversClass(Sets::class)]
#[CoversClass(Progress::class)]
#[CoversClass(Completion::class)]
#[CoversClass(SetCompleted::class)]
#[CoversClass(TierReached::class)]
#[CoversClass(DefinitionConflict::class)]
#[CoversClass(UnknownSet::class)]
final class SetsTest extends TestCase
{
    private InMemoryDefinitionStore $definitions;

    private InMemoryCompletionStore $completions;

    private RecordingDispatcher $events;

    private Sets $sets;

    private ResourceReference $holder;

    #[Override]
    protected function setUp(): void
    {
        $this->definitions = new InMemoryDefinitionStore();
        $this->completions = new InMemoryCompletionStore();
        $clock = new FrozenClock();
        $this->events = new RecordingDispatcher();
        $this->sets = new Sets($this->definitions, $this->completions, $clock, $this->events);
        $this->holder = ResourceReference::of('member', '1042');
    }

    private function starter(int $version = 1): SetDefinition
    {
        return new SetDefinition(
            'starter',
            $version,
            1 === $version ? ['a', 'b', 'c'] : ['a', 'b', 'c', 'd'],
            [new Tier('half', required: 2, rewards: [new Reward('points', 'xp', 5)]), new Tier('shiny', 2)],
            [new Reward('badge', 'starter')],
        );
    }

    public function testAVersionIsDefinedOnceAndAgainWithTheSameContentWithoutAWord(): void
    {
        self::assertTrue($this->sets->define($this->starter()));
        self::assertFalse($this->sets->define($this->starter()));
        self::assertTrue($this->sets->define($this->starter(2)));
        self::assertFalse($this->sets->define($this->starter()));
        self::assertSame(2, $this->sets->definition('starter')->version);
        self::assertSame(1, $this->sets->definition('starter', 1)->version);
    }

    public function testAVersionRewrittenIsRefused(): void
    {
        $this->sets->define($this->starter());

        $this->expectException(DefinitionConflict::class);
        $this->expectExceptionMessageIsOrContains('The set "starter" already has a version 1 with other content: a changed set takes a new version.');

        $this->sets->define(new SetDefinition('starter', 1, ['a']));
    }

    public function testAnOlderVersionIsRefused(): void
    {
        $this->sets->define($this->starter(3));

        $this->expectException(DefinitionConflict::class);
        $this->expectExceptionMessageIsOrContains('The set "starter" is at version 3; version 2 is older.');

        $this->sets->define($this->starter(2));
    }

    public function testAnOlderVersionWithOtherContentIsRefused(): void
    {
        $this->sets->define($this->starter());
        $this->sets->define($this->starter(2));

        $this->expectException(DefinitionConflict::class);
        $this->expectExceptionMessageIsOrContains('The set "starter" is at version 2; version 1 is older.');

        $this->sets->define(new SetDefinition('starter', 1, ['z']));
    }

    public function testAnUnknownSetIsSaid(): void
    {
        $this->sets->define($this->starter());

        try {
            $this->sets->definition('starter', 4);
            self::fail('No exception.');
        } catch (UnknownSet $unknownSet) {
            self::assertSame('The set "starter" has no version 4.', $unknownSet->getMessage());
        }

        $this->expectException(UnknownSet::class);
        $this->expectExceptionMessageIsOrContains('The set "other" is not defined.');

        $this->sets->progress('other', $this->holder, Holdings::none());
    }

    public function testProgressRecordsNothing(): void
    {
        $this->sets->define($this->starter());
        $progress = $this->sets->progress('starter', $this->holder, Holdings::of(['a', 'b', 'c']));

        self::assertTrue($progress->isComplete());
        self::assertFalse($progress->completedOnce());
        self::assertSame([], $progress->completions);
        self::assertSame([], $progress->recorded);
        self::assertSame([], $this->events->events);
    }

    public function testACompletionAndItsTiersAreRecordedAndToldOnce(): void
    {
        $this->sets->define($this->starter());

        $half = $this->sets->record('starter', $this->holder, Holdings::of(['a', 'b']));
        self::assertCount(1, $half->recorded);
        self::assertSame('half', $half->recorded[0]->tier);
        self::assertTrue($half->tierReachedOnce('half'));
        self::assertFalse($half->tierReachedOnce('shiny'));
        self::assertFalse($half->completedOnce());

        $full = $this->sets->record('starter', $this->holder, Holdings::withLevels(['a' => 2, 'b' => 2, 'c' => 2]));
        self::assertSame([null, 'shiny'], array_map(static fn (Completion $completion): ?string => $completion->tier, $full->recorded));
        self::assertTrue($full->completedOnce());
        self::assertSame(1, $full->completion()?->version);
        self::assertSame('2026-11-13T14:30:00.000000Z', $full->completion()->toArray()['at']);
        self::assertCount(3, $full->completions);

        $again = $this->sets->record('starter', $this->holder, Holdings::withLevels(['a' => 2, 'b' => 2, 'c' => 2]));
        self::assertSame([], $again->recorded);

        self::assertCount(3, $this->events->events);
        [$tier, $completed, $shiny] = $this->events->events;
        self::assertInstanceOf(TierReached::class, $tier);
        self::assertEquals([new Reward('points', 'xp', 5)], $tier->rewards);
        self::assertInstanceOf(SetCompleted::class, $completed);
        self::assertEquals([new Reward('badge', 'starter')], $completed->rewards);
        self::assertSame($full->recorded[0], $completed->completion);
        self::assertInstanceOf(TierReached::class, $shiny);
        self::assertSame([], $shiny->rewards);
        self::assertSame('shiny', $shiny->completion->tier);
    }

    public function testANewVersionNeverTakesACompletionBack(): void
    {
        $this->sets->define($this->starter());
        $this->sets->record('starter', $this->holder, Holdings::of(['a', 'b', 'c']));
        $this->sets->define($this->starter(2));

        $progress = $this->sets->record('starter', $this->holder, Holdings::of(['a', 'b', 'c']));

        self::assertFalse($progress->isComplete());
        self::assertTrue($progress->completedOnce());
        self::assertTrue($progress->completedUnderEarlierVersion());
        self::assertSame([], $progress->recorded);
        self::assertSame(75, $progress->evaluation->percent());
        self::assertSame(['d'], $progress->evaluation->missing);

        $completed = $this->sets->record('starter', $this->holder, Holdings::of(['a', 'b', 'c', 'd']));
        self::assertFalse($completed->completedUnderEarlierVersion());
        self::assertSame(1, $completed->completion()?->version);
        self::assertCount(1, array_filter($this->events->events, static fn (object $event): bool => $event instanceof SetCompleted));
    }

    public function testACompletionUnderTheLatestVersionIsNotEarlier(): void
    {
        $this->sets->define($this->starter());
        $this->sets->record('starter', $this->holder, Holdings::of(['a', 'b', 'c']));

        $progress = $this->sets->progress('starter', $this->holder, Holdings::of(['a']));

        self::assertFalse($progress->isComplete());
        self::assertFalse($progress->completedUnderEarlierVersion());
        self::assertFalse(new Progress($progress->evaluation, [])->completedUnderEarlierVersion());
    }

    public function testCompletionsAreKeptPerHolder(): void
    {
        $other = ResourceReference::of('member', '7');
        $this->sets->define($this->starter());
        $this->sets->record('starter', $this->holder, Holdings::of(['a', 'b', 'c']));

        self::assertSame([], $this->sets->progress('starter', $other, Holdings::none())->completions);
        self::assertCount(2, $this->sets->record('starter', $other, Holdings::of(['a', 'b', 'c']))->recorded);
        self::assertSame(2, $this->sets->eraseHolder($this->holder));
        self::assertSame(0, $this->sets->eraseHolder($this->holder));
        self::assertCount(2, $this->completions->ofHolder($other));
    }

    public function testAHolderReferencedByAnEmailIsRefused(): void
    {
        $this->sets->define($this->starter());

        try {
            $this->sets->progress('starter', ResourceReference::of('member', 'a@b.c'), Holdings::none());
            self::fail('No exception.');
        } catch (InvalidSet) {
        }

        $this->expectException(InvalidSet::class);

        $this->sets->record('starter', ResourceReference::of('member', 'a@b.c'), Holdings::none());
    }

    public function testTheSystemClockAndNoDispatcherAreTheDefaults(): void
    {
        $sets = new Sets($this->definitions, $this->completions);
        $sets->define($this->starter());

        $before = new DateTimeImmutable();
        $progress = $sets->record('starter', $this->holder, Holdings::of(['a', 'b', 'c']));

        self::assertSame('UTC', $progress->recorded[0]->at->getTimezone()->getName());
        self::assertGreaterThanOrEqual($before->getTimestamp(), $progress->recorded[0]->at->getTimestamp());
    }
}
