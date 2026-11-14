<?php

declare(strict_types=1);

namespace Tilttek\Quiz\Tests\Unit;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\Quiz\QuestionTimer;

#[CoversClass(QuestionTimer::class)]
final class QuestionTimerTest extends TestCase
{
    public function testItCountsMillisecondsFromTheMomentShown(): void
    {
        $shown = new DateTimeImmutable('2026-11-12 20:00:00.000 UTC');
        $timer = new QuestionTimer($shown, 10);

        self::assertSame(0, $timer->elapsed($shown));
        self::assertSame(0, $timer->elapsed($shown->modify('-1 second')));
        self::assertSame(2500, $timer->elapsed($shown->modify('+2500 milliseconds')));
        self::assertSame(7500, $timer->remaining($shown->modify('+2500 milliseconds')));
        self::assertSame(0, $timer->remaining($shown->modify('+11 seconds')));
        self::assertFalse($timer->isOver($shown->modify('+10 seconds')));
        self::assertTrue($timer->isOver($shown->modify('+10001 milliseconds')));
        self::assertSame('2026-11-12T20:00:10+00:00', $timer->deadline()?->format(\DATE_ATOM));
    }

    public function testAnUntimedQuestionNeverRunsOut(): void
    {
        $shown = new DateTimeImmutable('2026-11-12 20:00:00 UTC');
        $timer = new QuestionTimer($shown, null);

        self::assertNull($timer->remaining($shown->modify('+1 day')));
        self::assertFalse($timer->isOver($shown->modify('+1 day')));
        self::assertNull($timer->deadline());
    }
}
