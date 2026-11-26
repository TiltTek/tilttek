<?php

declare(strict_types=1);

namespace Tilttek\ScoreSheets\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\ScoreSheets\BlankSheet;
use Tilttek\ScoreSheets\Exception\InvalidEntry;

#[CoversClass(BlankSheet::class)]
final class BlankSheetTest extends TestCase
{
    public function testABlankSheetIsNeutralData(): void
    {
        $blank = new BlankSheet(LanternHarbor::sheet(), 5)->toArray();

        self::assertSame('lantern-harbor', $blank['game']);
        self::assertSame(5, $blank['columns']);
        self::assertSame(['key' => 'boats', 'label' => 'Boats moored', 'entered' => true, 'counted' => false, 'min' => 0, 'max' => 12], $blank['lines'][0]);
        self::assertSame(['key' => 'harbor', 'label' => 'Harbour', 'entered' => false, 'counted' => true, 'operation' => ['type' => 'sum', 'of' => ['boats', 'lanterns']]], $blank['lines'][2]);
        self::assertCount(8, $blank['lines']);
        self::assertSame(['label' => 'total', 'of' => ['harbor', 'set_points', 'fleet', 'lighthouse']], $blank['total']);
        self::assertSame([['category' => 'coins', 'prefer' => 'most'], ['category' => 'boats', 'prefer' => 'fewest']], $blank['tiebreakers']);
        self::assertSame(4, new BlankSheet(LanternHarbor::sheet())->columns);
        self::assertSame(1, new BlankSheet(LanternHarbor::sheet(), 1)->columns);
        self::assertSame(100, new BlankSheet(LanternHarbor::sheet(), 100)->columns);
    }

    public function testABlankSheetHasOneToAHundredColumns(): void
    {
        foreach ([0, 101] as $columns) {
            try {
                new BlankSheet(LanternHarbor::sheet(), $columns);
                self::fail('A blank sheet out of bounds was accepted.');
            } catch (InvalidEntry $invalid) {
                self::assertSame('A blank sheet has 1 to 100 columns.', $invalid->getMessage());
            }
        }
    }
}
