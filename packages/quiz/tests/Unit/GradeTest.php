<?php

declare(strict_types=1);

namespace Tilttek\Quiz\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\Quiz\Exception\InvalidQuiz;
use Tilttek\Quiz\Grade;

#[CoversClass(Grade::class)]
#[CoversClass(InvalidQuiz::class)]
final class GradeTest extends TestCase
{
    public function testAGradeIsZeroToAThousandThousandths(): void
    {
        self::assertSame(0, Grade::of(0)->permille);
        self::assertSame(1000, Grade::of(1000)->permille);
        self::assertTrue(Grade::of(1000)->isRight());
        self::assertFalse(Grade::of(999)->isRight());
        self::assertTrue(Grade::of(0)->isWrong());
        self::assertFalse(Grade::of(1)->isWrong());
        self::assertTrue(Grade::right()->isRight());
        self::assertTrue(Grade::wrong()->isWrong());
    }

    public function testBelowZeroIsRefused(): void
    {
        $this->expectException(InvalidQuiz::class);
        $this->expectExceptionMessageIsOrContains('A grade is 0 to 1000 thousandths; -1 is not one.');
        Grade::of(-1);
    }

    public function testAboveAThousandIsRefused(): void
    {
        $this->expectException(InvalidQuiz::class);
        Grade::of(1001);
    }

    public function testAShareIsRoundedDownAndBounded(): void
    {
        self::assertSame(333, Grade::share(1, 3)->permille);
        self::assertSame(666, Grade::share(2, 3)->permille);
        self::assertSame(1000, Grade::share(3, 3)->permille);
        self::assertSame(1000, Grade::share(4, 3)->permille);
        self::assertSame(0, Grade::share(0, 3)->permille);
        self::assertSame(0, Grade::share(-2, 3)->permille);
        self::assertSame(0, Grade::share(1, 0)->permille);
        self::assertSame(1000, Grade::share(1, 1)->permille);
    }
}
