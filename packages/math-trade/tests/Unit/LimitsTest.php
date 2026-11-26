<?php

declare(strict_types=1);

namespace Tilttek\MathTrade\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\MathTrade\Exception\InvalidTradeList;
use Tilttek\MathTrade\Limits;

#[CoversClass(Limits::class)]
final class LimitsTest extends TestCase
{
    public function testHasDefaults(): void
    {
        $limits = new Limits();

        self::assertSame(2_000, $limits->participants);
        self::assertSame(5_000, $limits->offers);
        self::assertSame(100, $limits->offersPerParticipant);
        self::assertSame(500, $limits->wishesPerOffer);
        self::assertSame(100, $limits->groupSize);
        self::assertSame(250_000, $limits->wishedOffers);
        self::assertSame(8_388_608, $limits->jsonBytes);
    }

    public function testAcceptsTheBounds(): void
    {
        $lowest = new Limits(1, 1, 1, 1, 1, 1, 1);
        $highest = new Limits(100_000, 100_000, 100_000, 10_000, 10_000, 5_000_000, 268_435_456);

        self::assertSame([1, 1, 1, 1, 1, 1, 1], array_values(get_object_vars($lowest)));
        self::assertSame([100_000, 100_000, 100_000, 10_000, 10_000, 5_000_000, 268_435_456], array_values(get_object_vars($highest)));
    }

    /**
     * @return iterable<string, array{array<string, int>, string}>
     */
    public static function outOfBounds(): iterable
    {
        yield 'no participant' => [['participants' => 0], 'The limit of participants is 1 to 100000; it is 0.'];
        yield 'too many participants' => [['participants' => 100_001], 'The limit of participants is 1 to 100000; it is 100001.'];
        yield 'no offer' => [['offers' => 0], 'The limit of offers is 1 to 100000; it is 0.'];
        yield 'too many offers' => [['offers' => 100_001], 'The limit of offers is 1 to 100000; it is 100001.'];
        yield 'no offer per participant' => [['offersPerParticipant' => 0], 'The limit of offers per participant is 1 to 100000; it is 0.'];
        yield 'too many offers per participant' => [['offersPerParticipant' => 100_001], 'The limit of offers per participant is 1 to 100000; it is 100001.'];
        yield 'no wish' => [['wishesPerOffer' => 0], 'The limit of wishes per offer is 1 to 10000; it is 0.'];
        yield 'too many wishes' => [['wishesPerOffer' => 10_001], 'The limit of wishes per offer is 1 to 10000; it is 10001.'];
        yield 'an empty group' => [['groupSize' => 0], 'The limit of offers per group is 1 to 10000; it is 0.'];
        yield 'too large a group' => [['groupSize' => 10_001], 'The limit of offers per group is 1 to 10000; it is 10001.'];
        yield 'no wished offer' => [['wishedOffers' => 0], 'The limit of wished offers is 1 to 5000000; it is 0.'];
        yield 'too many wished offers' => [['wishedOffers' => 5_000_001], 'The limit of wished offers is 1 to 5000000; it is 5000001.'];
        yield 'an empty document' => [['jsonBytes' => 0], 'The limit of bytes of a JSON document is 1 to 268435456; it is 0.'];
        yield 'too long a document' => [['jsonBytes' => 268_435_457], 'The limit of bytes of a JSON document is 1 to 268435456; it is 268435457.'];
    }

    /**
     * @param array<string, int> $arguments
     */
    #[DataProvider('outOfBounds')]
    public function testRefusesALimitOutOfBounds(array $arguments, string $message): void
    {
        $this->expectException(InvalidTradeList::class);
        $this->expectExceptionMessageIsOrContains($message);

        new Limits(...$arguments);
    }
}
