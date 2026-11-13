<?php

declare(strict_types=1);

namespace Tilttek\WargameArmy\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\WargameArmy\ArmyList;
use Tilttek\WargameArmy\Catalogue;
use Tilttek\WargameArmy\Constraint\PointsShare;
use Tilttek\WargameArmy\Constraint\Scope;
use Tilttek\WargameArmy\Constraint\Selector;
use Tilttek\WargameArmy\Constraint\UnitCount;
use Tilttek\WargameArmy\Costing;
use Tilttek\WargameArmy\Detachment;
use Tilttek\WargameArmy\DetachmentType;
use Tilttek\WargameArmy\Exception\InvalidCatalogue;
use Tilttek\WargameArmy\Slot;
use Tilttek\WargameArmy\UnitEntry;
use Tilttek\WargameArmy\UnitSelection;
use Tilttek\WargameArmy\Violation;

#[CoversClass(Selector::class)]
#[CoversClass(Scope::class)]
#[CoversClass(UnitCount::class)]
#[CoversClass(PointsShare::class)]
final class ConstraintTest extends TestCase
{
    private Catalogue $catalogue;

    protected function setUp(): void
    {
        $this->catalogue = new Catalogue('c', 'C', '1', [
            new UnitEntry('hero', 'Hero', ['hero', 'leader'], 25),
            new UnitEntry('troop', 'Troop', ['troops'], 25),
        ], [new DetachmentType('d', 'D', [new Slot('hero'), new Slot('troops')])], 1, 3);
    }

    public function testASelectorMatchesAUnitOrACategory(): void
    {
        $hero = $this->catalogue->unit('hero');
        self::assertNotNull($hero);

        self::assertTrue(Selector::unit('hero')->matches($hero));
        self::assertFalse(Selector::unit('troop')->matches($hero));
        self::assertTrue(Selector::category('leader')->matches($hero));
        self::assertFalse(Selector::category('troops')->matches($hero));
        self::assertSame('"Hero"', Selector::unit('hero')->label($this->catalogue));
        self::assertSame('"ghost"', Selector::unit('ghost')->label($this->catalogue));
        self::assertSame('units of the category "leader"', Selector::category('leader')->label($this->catalogue));
        self::assertSame(['unit' => 'hero'], Selector::unit('hero')->toArray());
        self::assertSame(['category' => 'leader'], Selector::category('leader')->toArray());
        self::assertSame('hero', Selector::fromArray(['unit' => 'hero'])->unit);
        self::assertNull(Selector::fromArray(['unit' => 'hero'])->category);
        self::assertSame('leader', Selector::fromArray(['category' => 'leader'])->category);
        self::assertNull(Selector::fromArray(['category' => 'leader'])->unit);
    }

    public function testASelectorChecksItsCode(): void
    {
        $this->expectException(InvalidCatalogue::class);
        $this->expectExceptionMessageIsOrContains('The code of a category is');

        Selector::category('Leader');
    }

    public function testACountInTheArmy(): void
    {
        $count = new UnitCount(Selector::category('hero'), Scope::Army, 1, 2);

        self::assertSame(['At least 1 units of the category "hero" in the army; the army has 0.'], $this->messages($count, $this->list([], ['troop'])));
        self::assertSame([], $this->messages($count, $this->list(['hero'], ['troop'])));
        self::assertSame([], $this->messages($count, $this->list(['hero'], ['hero'])));
        self::assertSame(['At most 2 units of the category "hero" in the army; the army has 3.'], $this->messages($count, $this->list(['hero', 'hero'], ['hero', 'ghost'])));
        self::assertSame(['category' => 'hero', 'count' => 3, 'max' => 2], $count->check($this->list(['hero', 'hero'], ['hero']), $this->catalogue, $this->costing($this->list(['hero', 'hero'], ['hero'])))[0]->values);
        self::assertSame(['category' => 'hero', 'count' => 0, 'min' => 1], $count->check($this->list([]), $this->catalogue, $this->costing($this->list([])))[0]->values);
        self::assertSame(['type' => 'count', 'category' => 'hero', 'scope' => 'army', 'min' => 1, 'max' => 2], $count->toArray());
    }

    public function testACountInEachDetachment(): void
    {
        $count = new UnitCount(Selector::unit('hero'), Scope::Detachment, 1);

        self::assertSame(['At least 1 "Hero" in each detachment; detachment 2 has 0.'], $this->messages($count, $this->list(['hero'], ['troop'])));
        self::assertSame(['unit' => 'hero', 'count' => 0, 'detachment' => 2, 'min' => 1], $count->check($this->list(['hero'], ['troop']), $this->catalogue, $this->costing($this->list(['hero'], ['troop'])))[0]->values);
        self::assertSame([], $this->messages($count, $this->list(['hero'], ['hero', 'hero'])));
        self::assertSame(['At least 1 "Hero" in each detachment; detachment 1 has 0.', 'At least 1 "Hero" in each detachment; detachment 2 has 0.'], $this->messages($count, $this->list([], [])));
        self::assertNull($count->max);
        self::assertSame(['type' => 'count', 'unit' => 'hero', 'scope' => 'detachment', 'min' => 1, 'max' => null], $count->toArray());
        self::assertSame(['At most 1 "Hero" in each detachment; detachment 1 has 2.'], $this->messages(new UnitCount(Selector::unit('hero'), Scope::Detachment, max: 1), $this->list(['hero', 'hero'], ['hero'])));
    }

    public function testACountReadsFromAnArray(): void
    {
        $count = UnitCount::fromArray(['type' => 'count', 'unit' => 'hero', 'scope' => 'detachment', 'max' => 1]);

        self::assertSame(Scope::Detachment, $count->scope);
        self::assertSame(0, $count->min);
        self::assertSame(1, $count->max);
        self::assertSame(Scope::Army, UnitCount::fromArray(['type' => 'count', 'unit' => 'hero', 'min' => 1])->scope);
        self::assertSame(Scope::Army, new UnitCount(Selector::unit('hero'), min: 1)->scope);
    }

    public function testACountChecksItsBounds(): void
    {
        foreach ([
            [static fn (): UnitCount => new UnitCount(Selector::unit('hero')), 'A count has a "min" above 0 or a "max".'],
            [static fn (): UnitCount => new UnitCount(Selector::unit('hero'), min: -1, max: 1), 'The fewest units of a count is from 0 to 4000; -1 is not.'],
            [static fn (): UnitCount => new UnitCount(Selector::unit('hero'), min: 4_001), 'The fewest units of a count is from 0 to 4000; 4001 is not.'],
            [static fn (): UnitCount => new UnitCount(Selector::unit('hero'), min: 2, max: 1), 'The most units of a count is from 2 to 4000; 1 is not.'],
            [static fn (): UnitCount => new UnitCount(Selector::unit('hero'), max: 4_001), 'The most units of a count is from 0 to 4000; 4001 is not.'],
        ] as [$build, $message]) {
            try {
                $build();
                self::fail('The count was accepted.');
            } catch (InvalidCatalogue $exception) {
                self::assertSame($message, $exception->getMessage());
            }
        }

        self::assertSame(4_000, new UnitCount(Selector::unit('hero'), min: 4_000, max: 4_000)->max);
        self::assertSame(0, new UnitCount(Selector::unit('hero'), max: 0)->max);
    }

    public function testAPointsShareIsComparedExactly(): void
    {
        $share = new PointsShare(Selector::category('hero'), 20, 50);

        // Each unit costs 25 points; the limit is 100.
        self::assertSame([], $this->messages($share, $this->list(['hero', 'hero'], ['troop'])));
        self::assertSame(['Units of the category "hero" cost at most 50 % of the points limit, 50 points; the list spends 75.'], $this->messages($share, $this->list(['hero', 'hero', 'hero'])));
        self::assertSame(['Units of the category "hero" cost at least 20 % of the points limit, 20 points; the list spends 0.'], $this->messages($share, $this->list([], ['troop'])));
        self::assertSame([], $this->messages($share, $this->list(['hero'])));
        self::assertSame(['category' => 'hero', 'points' => 75, 'limit' => 100, 'max_percent' => 50], $share->check($this->list(['hero', 'hero', 'hero']), $this->catalogue, $this->costing($this->list(['hero', 'hero', 'hero'])))[0]->values);
        self::assertSame(['category' => 'hero', 'points' => 0, 'limit' => 100, 'min_percent' => 20], $share->check($this->list([]), $this->catalogue, $this->costing($this->list([])))[0]->values);
        self::assertSame(['type' => 'points-share', 'category' => 'hero', 'min_percent' => 20, 'max_percent' => 50], $share->toArray());

        $exact = new PointsShare(Selector::unit('hero'), 25, 25);
        self::assertSame([], $this->messages($exact, $this->list(['hero'])));
        self::assertSame(['"Hero" cost at least 25 % of the points limit, 25 points; the list spends 0.'], $this->messages($exact, $this->list([])));

        // 33 % of 101 points is 33.33: 33 points keep the share, 34 break it, and the least share rounds up.
        $thirds = new PointsShare(Selector::category('hero'), 33, 33);
        $list = new ArmyList('l', 'L', 'c', '1', 101, [new Detachment('d', null, [new UnitSelection('hero')])]);
        $catalogue = new Catalogue('c', 'C', '1', [new UnitEntry('hero', 'Hero', ['hero'], 34)], [new DetachmentType('d', 'D', [new Slot('hero')])]);
        self::assertSame(['Units of the category "hero" cost at most 33 % of the points limit, 33 points; the list spends 34.'], array_map(static fn (Violation $violation): string => $violation->message, $thirds->check($list, $catalogue, Costing::of($list, $catalogue))));
        $catalogue = new Catalogue('c', 'C', '1', [new UnitEntry('hero', 'Hero', ['hero'], 33)], [new DetachmentType('d', 'D', [new Slot('hero')])]);
        self::assertSame(['Units of the category "hero" cost at least 33 % of the points limit, 34 points; the list spends 33.'], array_map(static fn (Violation $violation): string => $violation->message, $thirds->check($list, $catalogue, Costing::of($list, $catalogue))));
    }

    public function testAPointsShareKeepsTheLeastBreakOfItsLimits(): void
    {
        $catalogue = new Catalogue('c', 'C', '1', [new UnitEntry('hero', 'Hero', ['hero'], 1), new UnitEntry('big', 'Big', ['big'], 600)], [new DetachmentType('d', 'D', [new Slot('hero'), new Slot('big')])]);
        $messages = (static fn (PointsShare $share, ArmyList $list): array => array_map(static fn (Violation $violation): string => $violation->message, $share->check($list, $catalogue, Costing::of($list, $catalogue))));

        // 1 point is more than 1 % of 99 points, which is 0.99.
        $one = new ArmyList('l', 'L', 'c', '1', 99, [new Detachment('d', null, [new UnitSelection('hero')])]);
        self::assertSame(['Units of the category "hero" cost at most 1 % of the points limit, 0 points; the list spends 1.'], $messages(new PointsShare(Selector::category('hero'), 0, 1), $one));

        // 50 % of 1000 points is 500 points.
        $big = new ArmyList('l', 'L', 'c', '1', 1_000, [new Detachment('d', null, [new UnitSelection('big')])]);
        self::assertSame(['Units of the category "big" cost at most 50 % of the points limit, 500 points; the list spends 600.'], $messages(new PointsShare(Selector::category('big'), 0, 50), $big));

        // 1 % of 101 points is 1.01: the least share rounds up to 2 points.
        $none = new ArmyList('l', 'L', 'c', '1', 101);
        self::assertSame(['Units of the category "hero" cost at least 1 % of the points limit, 2 points; the list spends 0.'], $messages(new PointsShare(Selector::category('hero'), 1, 100), $none));
    }

    public function testASelectorNeedsOneCodeOfOneKind(): void
    {
        foreach ([['unit' => 'a', 'category' => 'b'], ['unit' => 5], ['category' => 5], ['unit' => 'a', 'category' => 5], ['unit' => 5, 'category' => 'b'], []] as $object) {
            try {
                Selector::fromArray($object);
                self::fail('The selector was accepted.');
            } catch (InvalidCatalogue $invalidCatalogue) {
                self::assertSame('A constraint counts either a "unit" or a "category", given as a string.', $invalidCatalogue->getMessage());
            }
        }
    }

    public function testAPointsShareReadsFromAnArrayAndChecksItsBounds(): void
    {
        $share = PointsShare::fromArray(['type' => 'points-share', 'unit' => 'hero', 'max_percent' => 10]);

        self::assertSame(0, $share->minPercent);
        self::assertSame(10, $share->maxPercent);
        self::assertSame(100, PointsShare::fromArray(['type' => 'points-share', 'unit' => 'hero', 'min_percent' => 10])->maxPercent);
        self::assertSame(100, new PointsShare(Selector::unit('hero'), 100, 100)->minPercent);
        self::assertSame(0, new PointsShare(Selector::unit('hero'), 0, 0)->maxPercent);

        foreach ([
            [static fn (): PointsShare => new PointsShare(Selector::unit('hero')), 'A points share has a "min_percent" above 0 or a "max_percent" below 100.'],
            [static fn (): PointsShare => new PointsShare(Selector::unit('hero'), -1, 50), 'The least share of a points share is from 0 to 100; -1 is not.'],
            [static fn (): PointsShare => new PointsShare(Selector::unit('hero'), 101, 101), 'The least share of a points share is from 0 to 100; 101 is not.'],
            [static fn (): PointsShare => new PointsShare(Selector::unit('hero'), 50, 40), 'The most share of a points share is from 50 to 100; 40 is not.'],
            [static fn (): PointsShare => new PointsShare(Selector::unit('hero'), 0, 101), 'The most share of a points share is from 0 to 100; 101 is not.'],
        ] as [$build, $message]) {
            try {
                $build();
                self::fail('The share was accepted.');
            } catch (InvalidCatalogue $exception) {
                self::assertSame($message, $exception->getMessage());
            }
        }
    }

    /**
     * @return list<string>
     */
    private function messages(UnitCount|PointsShare $constraint, ArmyList $list): array
    {
        return array_map(static fn (Violation $violation): string => $violation->message, $constraint->check($list, $this->catalogue, $this->costing($list)));
    }

    private function costing(ArmyList $list): Costing
    {
        return Costing::of($list, $this->catalogue);
    }

    /**
     * @param list<string> ...$detachments the units of each detachment
     */
    private function list(array ...$detachments): ArmyList
    {
        return new ArmyList('l', 'L', 'c', '1', 100, array_map(
            static fn (array $units): Detachment => new Detachment('d', null, array_map(static fn (string $unit): UnitSelection => new UnitSelection($unit), $units)),
            array_values($detachments),
        ));
    }
}
