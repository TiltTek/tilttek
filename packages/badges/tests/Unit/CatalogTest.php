<?php

declare(strict_types=1);

namespace Tilttek\Badges\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\Badges\Badge;
use Tilttek\Badges\Catalog;
use Tilttek\Badges\Criterion\AtLeast;
use Tilttek\Badges\Criterion\Count;
use Tilttek\Badges\Criterion\Manual;
use Tilttek\Badges\Criterion\Measure;
use Tilttek\Badges\Criterion\Scope;
use Tilttek\Badges\Criterion\Streak;
use Tilttek\Badges\Exception\InvalidBadge;
use Tilttek\Badges\Exception\UnknownBadge;
use Tilttek\Badges\Tier;

#[CoversClass(Catalog::class)]
final class CatalogTest extends TestCase
{
    public function testTheCatalogHoldsItsBadges(): void
    {
        $gold = new Badge('gold', 'Gold', new Count(new Scope(['news.commented']), 50), tier: new Tier('commenter', 3));
        $bronze = new Badge('bronze', 'Bronze', new Count(new Scope(['news.commented', 'news.liked']), 5), tier: new Tier('commenter', 1));
        $silver = new Badge('silver', 'Silver', new Count(new Scope(['news.commented']), 20), tier: new Tier('commenter', 2));
        $visitor = new Badge('visitor', 'Visitor', new AtLeast([new Count(new Scope(['site.visited'])), new Manual()]), tier: new Tier('visits', 1));
        $hand = new Badge('hand', 'Hand', new Manual());
        $catalog = new Catalog([$gold, $bronze, $visitor, $silver, $hand]);

        self::assertSame([$gold, $bronze, $visitor, $silver, $hand], $catalog->all());
        self::assertSame($silver, $catalog->get('silver'));
        self::assertTrue($catalog->has('hand'));
        self::assertFalse($catalog->has('platinum'));
        self::assertSame([$bronze, $silver, $gold], $catalog->series('commenter'));
        self::assertSame([$visitor], $catalog->series('visits'));
        self::assertSame([], $catalog->series('none'));
        self::assertSame(['news.commented', 'news.liked', 'site.visited'], $catalog->patterns());
        self::assertSame(['r.0'], array_keys($catalog->leaves($visitor)));
        self::assertSame([], $catalog->leaves($hand));
        self::assertSame([], $catalog->leaves(new Badge('stranger', 'Stranger', new Count(new Scope(['a'])))));
        self::assertFalse($catalog->measures());
        self::assertTrue(new Catalog([$hand, new Badge('rich', 'Rich', new AtLeast([new Manual(), new Measure(new Scope(['a']), 'points', 10)]))])->measures());
        self::assertSame([], new Catalog()->all());
    }

    public function testAnUnknownBadge(): void
    {
        $this->expectExceptionObject(UnknownBadge::named('platinum'));
        new Catalog()->get('platinum');
    }

    public function testACodeIsHeldOnce(): void
    {
        $this->expectExceptionObject(InvalidBadge::because('The badge "gold" is in the catalog twice.'));
        new Catalog([new Badge('gold', 'Gold', new Manual()), new Badge('gold', 'Gold again', new Manual())]);
    }

    public function testARankIsHeldOnce(): void
    {
        $this->expectExceptionObject(InvalidBadge::because('The badges "gold" and "or" hold the same rank 3 of the series "commenter".'));
        new Catalog([new Badge('gold', 'Gold', new Manual(), tier: new Tier('commenter', 3)), new Badge('silver', 'Silver', new Manual(), tier: new Tier('commenter', 2)), new Badge('or', 'Or', new Manual(), tier: new Tier('commenter', 3))]);
    }

    public function testAPathHasALimit(): void
    {
        $criterion = new Count(new Scope(['a']));

        for ($depth = 0; $depth < 31; ++$depth) {
            $criterion = new AtLeast([$criterion]);
        }

        $widest = new AtLeast([...array_fill(0, 10, new Manual()), new Count(new Scope(['a']))]);

        for ($depth = 0; $depth < 30; ++$depth) {
            $widest = new AtLeast([$widest]);
        }

        $catalog = new Catalog([new Badge('wide', 'Wide', $widest)]);
        self::assertSame(['r'.str_repeat('.0', 30).'.10'], array_keys($catalog->leaves($catalog->get('wide'))));

        $this->expectExceptionObject(InvalidBadge::because(\sprintf('The criterion of the badge "deep" nests too deep: the path "r%s" is longer than 64 bytes.', str_repeat('.0', 32))));
        new Catalog([new Badge('deep', 'Deep', new AtLeast([$criterion]))]);
    }

    public function testTheCatalogIsReadFromPlainValues(): void
    {
        $catalog = Catalog::fromArray([
            'commenter.bronze' => [
                'name' => 'badges.commenter.bronze.name',
                'description' => 'badges.commenter.bronze.description',
                'image' => '/images/bronze.svg',
                'repeatable' => true,
                'hidden' => true,
                'revocable' => true,
                'tier' => ['series' => 'commenter', 'rank' => 1],
                'criterion' => ['count' => ['verbs' => ['news.commented'], 'times' => 5]],
            ],
            'streak' => ['criterion' => ['streak' => ['verbs' => ['site.visited'], 'days' => 7]]],
        ], 'America/Toronto');

        $bronze = $catalog->get('commenter.bronze');
        self::assertSame('badges.commenter.bronze.name', $bronze->name);
        self::assertSame('badges.commenter.bronze.description', $bronze->description);
        self::assertSame('/images/bronze.svg', $bronze->image);
        self::assertTrue($bronze->repeatable);
        self::assertTrue($bronze->hidden);
        self::assertTrue($bronze->revocable);
        self::assertSame(['commenter', 1], [$bronze->tier?->series, $bronze->tier?->rank]);
        self::assertInstanceOf(Count::class, $bronze->criterion);

        $streak = $catalog->get('streak');
        self::assertSame('streak', $streak->name);
        self::assertSame('', $streak->description);
        self::assertNull($streak->image);
        self::assertFalse($streak->repeatable);
        self::assertFalse($streak->hidden);
        self::assertFalse($streak->revocable);
        self::assertNull($streak->tier);
        self::assertInstanceOf(Streak::class, $streak->criterion);
        self::assertSame('America/Toronto', $streak->criterion->timezone->getName());
        self::assertFalse(Catalog::fromArray(['a' => ['repeatable' => 'yes', 'criterion' => ['manual' => true]]])->get('a')->repeatable);
    }

    public function testTheDefaultTimezoneIsUtc(): void
    {
        $criterion = Catalog::fromArray(['s' => ['criterion' => ['streak' => ['verbs' => ['a'], 'days' => 2]]]])->get('s')->criterion;
        self::assertInstanceOf(Streak::class, $criterion);
        self::assertSame('UTC', $criterion->timezone->getName());
    }

    /**
     * @return iterable<string, array{array<array-key, mixed>, string}>
     */
    public static function invalid(): iterable
    {
        yield 'by position' => [[['criterion' => ['manual' => true]]], 'The badges of a catalog are values by code.'];
        yield 'not values' => [['a' => 'manual'], 'The badges of a catalog are values by code.'];
        yield 'no criterion' => [['a' => ['name' => 'A']], 'The badge "a" has a criterion.'];
        yield 'name not a string' => [['a' => ['name' => 3, 'criterion' => ['manual' => true]]], 'The "name" of the badge "a" is a string.'];
        yield 'tier without series' => [['a' => ['tier' => ['rank' => 1], 'criterion' => ['manual' => true]]], '"" is not a series name: 1 to 64 lower-case letters, digits, "_", "." or "-", starting with a letter.'];
        yield 'tier without rank' => [['a' => ['tier' => ['series' => 's'], 'criterion' => ['manual' => true]]], 'The rank of a tier starts at 1; 0 is not one.'];
        yield 'tier rank a string' => [['a' => ['tier' => ['series' => 's', 'rank' => '2'], 'criterion' => ['manual' => true]]], 'The rank of a tier starts at 1; 0 is not one.'];
    }

    /**
     * @param array<array-key, mixed> $badges
     */
    #[DataProvider('invalid')]
    public function testAnInvalidCatalogIsRefused(array $badges, string $message): void
    {
        $this->expectExceptionObject(InvalidBadge::because($message));
        Catalog::fromArray($badges);
    }

    public function testATimezoneIsChecked(): void
    {
        $this->expectExceptionObject(InvalidBadge::because('"Mars/Olympus" is not a timezone.'));
        Catalog::fromArray([], 'Mars/Olympus');
    }
}
