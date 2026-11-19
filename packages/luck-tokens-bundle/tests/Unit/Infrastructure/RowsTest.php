<?php

declare(strict_types=1);

namespace Tilttek\LuckTokensBundle\Tests\Unit\Infrastructure;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Tilttek\LuckTokens\Exception\InvalidTokenRule;
use Tilttek\LuckTokensBundle\Infrastructure\Doctrine\Rows;

#[CoversClass(Rows::class)]
final class RowsTest extends TestCase
{
    public function testAnIntegerIsReadAsTheDriversGiveIt(): void
    {
        self::assertSame([42, 42, -7, 0, 999_999_999_999_999_999], [Rows::integer(42), Rows::integer('42'), Rows::integer('-7'), Rows::integer('0'), Rows::integer('999999999999999999')]);
        self::assertSame('member:ana', Rows::text('member:ana'));
    }

    #[TestWith(['4.2'])]
    #[TestWith([' 42'])]
    #[TestWith(['42 '])]
    #[TestWith(["42\n"])]
    #[TestWith(['x42'])]
    #[TestWith(['42x'])]
    #[TestWith([''])]
    #[TestWith(['1000000000000000000'])]
    #[TestWith([4.2])]
    #[TestWith([null])]
    public function testAnythingElseIsNotAnInteger(mixed $value): void
    {
        $this->expectException(InvalidTokenRule::class);
        $this->expectExceptionMessageIsOrContains('A column of the tokens holds an integer.');

        Rows::integer($value);
    }

    public function testATextIsAText(): void
    {
        $this->expectException(InvalidTokenRule::class);
        $this->expectExceptionMessageIsOrContains('A column of the tokens holds a text.');

        Rows::text(42);
    }
}
