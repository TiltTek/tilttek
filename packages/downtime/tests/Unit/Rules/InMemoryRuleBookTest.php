<?php

declare(strict_types=1);

namespace Tilttek\Downtime\Tests\Unit\Rules;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\Downtime\Activity;
use Tilttek\Downtime\Exception\InvalidDowntime;
use Tilttek\Downtime\Rules\InMemoryRuleBook;
use Tilttek\Downtime\SystemRules;
use Tilttek\Downtime\Tests\Unit\Fixture\Rules;

#[CoversClass(InMemoryRuleBook::class)]
final class InMemoryRuleBookTest extends TestCase
{
    public function testTheRuleBookFindsTheActivitiesOfEachSystem(): void
    {
        $book = new InMemoryRuleBook(Rules::system(), new SystemRules('space', [Rules::training(10)]));

        self::assertSame('crafting', $book->activity(Rules::SYSTEM, 'crafting')?->key);
        self::assertSame(10, $book->activity('space', 'training')?->days);
        self::assertNull($book->activity('space', 'crafting'));
        self::assertNull($book->activity('horror', 'training'));
        self::assertSame(['crafting', 'training'], array_map(static fn (Activity $activity): string => $activity->key, $book->activities(Rules::SYSTEM)));
        self::assertSame([], $book->activities('horror'));
        self::assertSame([], new InMemoryRuleBook()->activities(Rules::SYSTEM));
    }

    public function testASystemHasOneSetOfRules(): void
    {
        $this->expectException(InvalidDowntime::class);
        $this->expectExceptionMessageIsOrContains('The rules of the system fantasy are given twice.');

        new InMemoryRuleBook(Rules::system(), new SystemRules('space', [Rules::training()]), Rules::system());
    }
}
