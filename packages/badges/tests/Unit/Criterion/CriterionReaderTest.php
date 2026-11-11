<?php

declare(strict_types=1);

namespace Tilttek\Badges\Tests\Unit\Criterion;

use DateTimeZone;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\Badges\Criterion\AtLeast;
use Tilttek\Badges\Criterion\Count;
use Tilttek\Badges\Criterion\CriterionReader;
use Tilttek\Badges\Criterion\Manual;
use Tilttek\Badges\Criterion\Measure;
use Tilttek\Badges\Criterion\Streak;
use Tilttek\Badges\Criterion\Total;
use Tilttek\Badges\Exception\InvalidBadge;

#[CoversClass(CriterionReader::class)]
final class CriterionReaderTest extends TestCase
{
    public function testEveryKindIsRead(): void
    {
        $reader = new CriterionReader(new DateTimeZone('America/Toronto'));

        $count = $reader->read(['count' => ['verbs' => ['news.commented'], 'times' => 10, 'context' => ['system' => 'dnd5e', 'level' => 2, 'first' => true], 'since' => '2026-11-01', 'until' => '2026-12-01']]);
        self::assertInstanceOf(Count::class, $count);
        self::assertSame(10, $count->times);
        self::assertSame(['news.commented'], $count->scope->patterns);
        self::assertSame(['first' => true, 'level' => 2, 'system' => 'dnd5e'], $count->scope->conditions->values);
        self::assertSame('2026-11-01T00:00:00-04:00', $count->scope->since?->format(\DATE_ATOM));
        self::assertSame('2026-12-01T00:00:00-05:00', $count->scope->until?->format(\DATE_ATOM));

        $first = $reader->read(['first' => ['verbs' => ['sheet.published']]]);
        self::assertInstanceOf(Count::class, $first);
        self::assertSame(1, $first->times);
        self::assertNull($first->scope->since);
        self::assertSame([], $first->scope->conditions->values);

        $total = $reader->read(['total' => ['verbs' => ['book.read'], 'amount' => 1000]]);
        self::assertInstanceOf(Total::class, $total);
        self::assertSame(1000, $total->amount);

        $streak = $reader->read(['streak' => ['verbs' => ['site.visited'], 'days' => 7]]);
        self::assertInstanceOf(Streak::class, $streak);
        self::assertSame(7, $streak->days);
        self::assertSame('America/Toronto', $streak->timezone->getName());

        $measure = $reader->read(['measure' => ['name' => 'points', 'threshold' => 1000, 'verbs' => ['game.*']]]);
        self::assertInstanceOf(Measure::class, $measure);
        self::assertSame(['points', 1000], [$measure->name, $measure->threshold]);

        self::assertInstanceOf(Manual::class, $reader->read(['manual' => true]));
        self::assertInstanceOf(Manual::class, $reader->read(['manual' => null]));

        $all = $reader->read(['all' => [['manual' => true], ['first' => ['verbs' => ['a']]]]]);
        self::assertInstanceOf(AtLeast::class, $all);
        self::assertSame(2, $all->required);
        self::assertInstanceOf(Manual::class, $all->criteria[0]);
        self::assertInstanceOf(Count::class, $all->criteria[1]);

        $some = $reader->read(['at_least' => ['count' => 1, 'of' => [['manual' => true], ['manual' => true]]]]);
        self::assertInstanceOf(AtLeast::class, $some);
        self::assertSame(1, $some->required);
        self::assertCount(2, $some->criteria);
    }

    public function testTheDefaultTimezoneIsUtc(): void
    {
        $streak = new CriterionReader()->read(['streak' => ['verbs' => ['a'], 'days' => 2, 'since' => '2026-11-01']]);
        self::assertInstanceOf(Streak::class, $streak);
        self::assertSame('UTC', $streak->timezone->getName());
        self::assertSame('2026-11-01T00:00:00+00:00', $streak->scope->since?->format(\DATE_ATOM));
    }

    /**
     * @return iterable<string, array{array<array-key, mixed>, string}>
     */
    public static function invalid(): iterable
    {
        $alone = 'A criterion is one of count, total, first, streak, measure, manual, all and at_least, alone.';

        yield 'nothing' => [[], $alone];
        yield 'two kinds' => [['manual' => true, 'first' => ['verbs' => ['a']]], $alone];
        yield 'unknown kind' => [['ever' => []], '"ever" is not a kind of criterion: count, total, first, streak, measure, manual, all or at_least.'];
        yield 'values not a list' => [['count' => 3], 'The criterion "count" holds a list of values.'];
        yield 'no verbs' => [['first' => []], 'A criterion names its verbs as a list of patterns, and its context as values by name.'];
        yield 'verbs not a list' => [['first' => ['verbs' => 'a']], 'A criterion names its verbs as a list of patterns, and its context as values by name.'];
        yield 'context not a list' => [['first' => ['verbs' => ['a'], 'context' => 'x']], 'A criterion names its verbs as a list of patterns, and its context as values by name.'];
        yield 'verb not a string' => [['first' => ['verbs' => ['a', 3]]], 'A criterion names its verbs as a list of patterns.'];
        yield 'context by position' => [['first' => ['verbs' => ['a'], 'context' => ['x']]], 'The context of a criterion holds integers, booleans and strings by name.'];
        yield 'context of a list' => [['first' => ['verbs' => ['a'], 'context' => ['x' => [1]]]], 'The context of a criterion holds integers, booleans and strings by name.'];
        yield 'context of a float' => [['first' => ['verbs' => ['a'], 'context' => ['x' => 1.5]]], 'The context of a criterion holds integers, booleans and strings by name.'];
        yield 'times missing' => [['count' => ['verbs' => ['a']]], 'The "times" of a criterion is an integer.'];
        yield 'times a string' => [['count' => ['verbs' => ['a'], 'times' => '3']], 'The "times" of a criterion is an integer.'];
        yield 'name missing' => [['measure' => ['verbs' => ['a'], 'threshold' => 3]], 'The "name" of a criterion is a string.'];
        yield 'since not a string' => [['first' => ['verbs' => ['a'], 'since' => 3]], 'The "since" of a criterion is a string.'];
        yield 'since not a date' => [['first' => ['verbs' => ['a'], 'since' => 'not a date']], 'The "since" of a criterion is a date; "not a date" is not.'];
        yield 'all not a list' => [['all' => ['a' => ['manual' => true]]], 'A combination holds a list of criteria.'];
        yield 'all of a string' => [['all' => ['manual']], 'A combination holds a list of criteria.'];
        yield 'at least of nothing' => [['at_least' => ['count' => 1]], 'A combination holds a list of criteria.'];
        yield 'nested invalid' => [['all' => [['ever' => []]]], '"ever" is not a kind of criterion: count, total, first, streak, measure, manual, all or at_least.'];
    }

    /**
     * @param array<array-key, mixed> $criterion
     */
    #[DataProvider('invalid')]
    public function testAnInvalidCriterionIsRefused(array $criterion, string $message): void
    {
        $this->expectExceptionObject(InvalidBadge::because($message));
        new CriterionReader()->read($criterion);
    }
}
