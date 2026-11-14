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
        self::assertTrue($summary->hasEnoughAnswers());
        self::assertSame(3, $summary->threshold);
        self::assertSame(0, FeedbackSummary::of([])->respondents);
        self::assertFalse(FeedbackSummary::of([])->hasEnoughAnswers());
    }

    public function testASummaryOfTooFewAnswersHidesThem(): void
    {
        $one = FeedbackSummary::of([new StarsAndWishes(['the heist'], ['zeppelins'])]);
        $two = FeedbackSummary::of([new StarsAndWishes(['the heist']), new StarsAndWishes([], []), new StarsAndWishes([], ['a map'])]);

        foreach ([$one, $two] as $summary) {
            self::assertSame([], $summary->stars);
            self::assertSame([], $summary->wishes);
            self::assertSame(0, $summary->respondents);
            self::assertFalse($summary->hasEnoughAnswers());
        }
    }

    public function testOneMoreAnswerPastTheThresholdChangesNothing(): void
    {
        $given = [
            new StarsAndWishes(['a']),
            new StarsAndWishes(['b']),
            new StarsAndWishes([], []),
            new StarsAndWishes(['c']),
            new StarsAndWishes(['d'], ['only d']),
            new StarsAndWishes(['e']),
            new StarsAndWishes(['f']),
        ];
        $summaries = [];

        for ($answers = 1; $answers <= \count($given); ++$answers) {
            $summaries[$answers] = FeedbackSummary::of(\array_slice($given, 0, $answers));
        }

        self::assertFalse($summaries[3]->hasEnoughAnswers());
        self::assertSame(['a', 'b', 'c'], $summaries[4]->stars);
        self::assertEquals($summaries[4], $summaries[5]);
        self::assertEquals($summaries[4], $summaries[6]);
        self::assertSame(['a', 'b', 'c', 'd', 'e', 'f'], $summaries[7]->stars);
        self::assertSame(['only d'], $summaries[7]->wishes);
        self::assertSame(6, $summaries[7]->respondents);
    }

    public function testTheThresholdIsChosen(): void
    {
        $alone = FeedbackSummary::of([new StarsAndWishes(['the heist'])], 1);

        self::assertSame(['the heist'], $alone->stars);
        self::assertSame(1, $alone->respondents);
        self::assertSame(1, $alone->threshold);
        self::assertSame(2, FeedbackSummary::of([new StarsAndWishes(['a']), new StarsAndWishes(['b']), new StarsAndWishes(['c'])], 2)->respondents);

        FeedbackSummary::assertThreshold(1);

        $this->expectException(InvalidAnswer::class);
        $this->expectExceptionMessageIsOrContains('A summary waits for at least 1 answer, not 0.');

        FeedbackSummary::of([], 0);
    }
}
