<?php

declare(strict_types=1);

namespace Tilttek\TableSafety\Tests\Unit\Feedback;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\TableSafety\Exception\InvalidAnswer;
use Tilttek\TableSafety\Feedback\Feedback;
use Tilttek\TableSafety\Feedback\FeedbackSummary;
use Tilttek\TableSafety\Feedback\StarsAndWishes;

#[CoversClass(StarsAndWishes::class)]
#[CoversClass(Feedback::class)]
#[CoversClass(FeedbackSummary::class)]
final class FeedbackTest extends TestCase
{
    public function testTextsAreTrimmedAndEmptyOnesLeftOut(): void
    {
        $feedback = new StarsAndWishes([' The heist ', '', '  '], ['More downtime']);

        self::assertSame(['The heist'], $feedback->stars);
        self::assertSame(['More downtime'], $feedback->wishes);
        self::assertFalse($feedback->isEmpty());
        self::assertTrue(new StarsAndWishes([' '], [''])->isEmpty());
        self::assertFalse(new StarsAndWishes([], ['x'])->isEmpty());
        self::assertFalse(new StarsAndWishes(['x'])->isEmpty());
    }

    public function testTheyAreBounded(): void
    {
        $ten = array_fill(0, 10, str_repeat('é', 500));
        $feedback = new StarsAndWishes($ten, $ten);

        self::assertCount(10, $feedback->stars);
        self::assertCount(10, $feedback->wishes);
        self::assertCount(10, new StarsAndWishes([...$ten, ' '])->stars);
    }

    public function testTooManyStarsAreRefused(): void
    {
        $this->expectException(InvalidAnswer::class);
        $this->expectExceptionMessageIsOrContains('At most 10 stars are given.');

        new StarsAndWishes(array_fill(0, 11, 'x'));
    }

    public function testALongWishIsRefused(): void
    {
        $this->expectException(InvalidAnswer::class);
        $this->expectExceptionMessageIsOrContains('Each of the wishes has at most 500 characters.');

        new StarsAndWishes([], [str_repeat('é', 501)]);
    }

    public function testTheyAreStoredAsAnArray(): void
    {
        $feedback = new StarsAndWishes(['a'], ['b', 'c']);

        self::assertSame(['stars' => ['a'], 'wishes' => ['b', 'c']], $feedback->toArray());
        self::assertEquals($feedback, StarsAndWishes::fromArray($feedback->toArray()));
        self::assertEquals(new StarsAndWishes(['a'], []), StarsAndWishes::fromArray(['stars' => ['x' => 'a', 2], 'wishes' => 'b']));
        self::assertTrue(StarsAndWishes::fromArray([])->isEmpty());
    }

    public function testAFeedbackNamesItsSession(): void
    {
        $feedback = new Feedback(ResourceReference::of('table', '7'), '2026-11-12', ResourceReference::of('member', 'ana'), new StarsAndWishes(['a']), new DateTimeImmutable('2026-11-12'));

        self::assertSame('2026-11-12', $feedback->session);

        $this->expectException(InvalidAnswer::class);
        $this->expectExceptionMessageIsOrContains('"Session 3" is not a session key.');

        Feedback::assertSession('Session 3');
    }

    public function testTheSummaryKeepsNoAuthorNorOrder(): void
    {
        $summary = FeedbackSummary::of([
            new StarsAndWishes(['the heist', 'Bards'], ['zeppelins']),
            new StarsAndWishes([], []),
            new StarsAndWishes(['apples'], ['More downtime']),
            new StarsAndWishes([], ['a map']),
        ]);

        self::assertSame(['apples', 'Bards', 'the heist'], $summary->stars);
        self::assertSame(['a map', 'More downtime', 'zeppelins'], $summary->wishes);
        self::assertSame(3, $summary->respondents);
        self::assertSame(0, FeedbackSummary::of([])->respondents);
    }
}
