<?php

declare(strict_types=1);

namespace Tilttek\TableSafety\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\TableSafety\AgreementSummary;
use Tilttek\TableSafety\Exception\InvalidAnswer;
use Tilttek\TableSafety\Feedback\FeedbackSummary;
use Tilttek\TableSafety\RespondentThreshold;

#[CoversClass(RespondentThreshold::class)]
#[CoversClass(AgreementSummary::class)]
final class RespondentThresholdTest extends TestCase
{
    public function testASummaryCountsWholeGroups(): void
    {
        self::assertSame(3, RespondentThreshold::DEFAULT);
        self::assertSame(RespondentThreshold::DEFAULT, FeedbackSummary::DEFAULT_THRESHOLD);
        self::assertSame(0, RespondentThreshold::counted(0, 3));
        self::assertSame(0, RespondentThreshold::counted(2, 3));
        self::assertSame(3, RespondentThreshold::counted(3, 3));
        self::assertSame(3, RespondentThreshold::counted(5, 3));
        self::assertSame(6, RespondentThreshold::counted(6, 3));
        self::assertSame(1, RespondentThreshold::counted(1, 1));
    }

    public function testAThresholdOfOneIsTheLeast(): void
    {
        RespondentThreshold::assert(1);

        $this->expectException(InvalidAnswer::class);
        $this->expectExceptionMessageIsOrContains('A summary waits for at least 1 answer, not -2.');

        AgreementSummary::of([], -2);
    }
}
