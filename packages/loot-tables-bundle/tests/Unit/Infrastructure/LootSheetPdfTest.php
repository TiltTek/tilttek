<?php

declare(strict_types=1);

namespace Tilttek\LootTablesBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Phil\PagedMedia\Font\StandardFont;
use Phil\PagedMedia\Pdf\Document;
use Phil\PagedMedia\Pdf\PageSize;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Tilttek\LootTables\Draw\LootDrawer;
use Tilttek\LootTablesBundle\Application\LootCommitments;
use Tilttek\LootTablesBundle\Application\LootOptionDrawer;
use Tilttek\LootTablesBundle\Application\LootPrint;
use Tilttek\LootTablesBundle\Application\LootResults;
use Tilttek\LootTablesBundle\Application\PhysicalDice;
use Tilttek\LootTablesBundle\Application\SecureSeeds;
use Tilttek\LootTablesBundle\Domain\Model\LootDraw;
use Tilttek\LootTablesBundle\Infrastructure\Memory\InMemoryLootDrawRepository;
use Tilttek\LootTablesBundle\Infrastructure\Pdf\LootSheetPdf;
use Tilttek\LootTablesBundle\Tests\Unit\Fixture\Clock;
use Tilttek\LootTablesBundle\Tests\Unit\Fixture\Shop;

#[CoversClass(LootSheetPdf::class)]
final class LootSheetPdfTest extends TestCase
{
    public function testEachDrawIsPrintedWithWhatGoesOnItsUnit(): void
    {
        $draws = new InMemoryLootDrawRepository();
        $order = Shop::order(['shirt' => 2, 'epitaph' => 1]);
        $commitments = new LootCommitments(Shop::drawers(), Shop::lineOptions(), $draws, new SecureSeeds(), Clock::at());

        $commitments->commit($order);

        $drawer = new LootOptionDrawer(Shop::drawers(), $commitments, $draws, new LootDrawer(), Clock::at());
        $shirt = Shop::line($order, 'shirt');
        $drawer->draw('loot-box:starter', $order, $shirt, 'loot', 1);
        $drawer->draw('loot-text', $order, Shop::line($order, 'epitaph'), 'epitaph', 1);

        $results = new LootResults(Shop::drawers(), $draws, new LootDrawer());
        $first = $draws->get(LootDraw::idOf('1042', $shirt->reference->value(), 'loot', 1));
        self::assertInstanceOf(LootDraw::class, $first);
        $faces = $results->result($first)?->faces();
        self::assertNotNull($faces);
        $epitaph = $results->ofOrder('1042')[2];

        $texts = $this->texts(new LootSheetPdf(new LootPrint($results))->render('1042', new DateTimeImmutable('2026-11-08 12:00:00')));

        $rings = ['Copper ring, finger', 'Silver ring, finger', 'Gold ring, finger', 'Bone ring, finger', 'Glass ring, finger', 'No ring'];
        $hats = ['Helmet, head', 'Hood, head', 'Crown, head', 'Cap, head', 'Pot, head', 'Bare head'];
        self::assertSame([
            ['F1', '16', 'Loot of order 1042'],
            ['F1', '10', $shirt->reference->value().', loot, unit 1'],
            ['F2', '10', \sprintf('Rings: %s (%d)', $rings[$faces[0] - 1], $faces[0])],
            ['F2', '10', \sprintf('Hats: %s (%d)', $hats[$faces[1] - 1], $faces[1])],
            ['F2', '10', 'Shop code: '.$faces[0].$faces[1]],
            ['F2', '10', 'Verified against its commitment'],
            ['F1', '10', $shirt->reference->value().', loot, unit 2'],
            ['F2', '10', 'Not drawn yet'],
            ['F1', '10', Shop::line($order, 'epitaph')->reference->value().', epitaph, unit 1'],
            ['F2', '10', 'Text: '.$epitaph->drawn()],
            ['F2', '10', 'Shop code: '.$results->result($epitaph)?->faces()[0]],
            ['F2', '10', 'Verified against its commitment'],
        ], $texts);
    }

    public function testPhysicalDiceAndADrawThatDoesNotCheckOut(): void
    {
        $draws = new InMemoryLootDrawRepository();
        $order = Shop::order(['shirt' => 1, 'giant' => 1]);
        $commitments = new LootCommitments(Shop::drawers(), Shop::lineOptions(), $draws, new SecureSeeds(), Clock::at());
        new PhysicalDice(Shop::drawers(), $draws, Clock::at())->enter($order, Shop::line($order, 'shirt')->reference, 'loot', ['36']);
        $giant = Shop::line($order, 'giant');
        $draw = $commitments->commitUnit($order, $giant, 'loot', 1, 'loot-box:giant');
        new LootOptionDrawer(Shop::drawers(), $commitments, $draws, new LootDrawer(), Clock::at())->draw('loot-box:giant', $order, $giant, 'loot', 1);
        $revealed = $draws->get($draw->id());
        self::assertInstanceOf(LootDraw::class, $revealed);
        new ReflectionProperty(LootDraw::class, 'proof')->setValue($revealed, ['seed' => 'none']);

        $texts = array_column($this->texts(new LootSheetPdf(new LootPrint(new LootResults(Shop::drawers(), $draws, new LootDrawer())), ['physical' => 'Dice of the register'])->render('1042')), 2);

        self::assertContains('Rings: Gold ring, finger (3)', $texts);
        self::assertContains('Hats: Bare head (6)', $texts);
        self::assertContains('Dice of the register', $texts);
        self::assertContains('Does not check out against its commitment', $texts);
        self::assertCount(1, (array) preg_grep('/^D21: Face \d+ \(\d+\)$/', $texts));
        self::assertSame([], preg_grep('/^Shop code: .$/', $texts));
    }

    public function testAnOrderWithoutDraws(): void
    {
        $texts = $this->texts(new LootSheetPdf(new LootPrint(new LootResults(Shop::drawers(), new InMemoryLootDrawRepository(), new LootDrawer())), ['title' => 'Butin de la commande %s'])->render('77'));

        self::assertSame([['F1', '16', 'Butin de la commande 77'], ['F2', '10', 'This order has no loot to draw.']], $texts);
    }

    public function testALongSheetTakesSeveralPages(): void
    {
        $draws = new InMemoryLootDrawRepository();
        $order = Shop::order(['shirt' => 60]);
        new LootCommitments(Shop::drawers(), Shop::lineOptions(), $draws, new SecureSeeds(), Clock::at())->commit($order);

        $pdf = $this->inflate(new LootSheetPdf(new LootPrint(new LootResults(Shop::drawers(), $draws, new LootDrawer())), size: PageSize::a5())->render('1042'));

        self::assertSame(1, preg_match('{/Type /Pages /Kids \[[^]]*\] /Count (\d+)}', $pdf, $count));
        self::assertSame(5, (int) $count[1]);
        preg_match_all('/BT \/F\d+ \d+ Tf [\d.]+ ([\d.]+) Td/', $pdf, $baselines);
        self::assertNotSame([], $baselines[1]);
        self::assertSame([], array_filter($baselines[1], static fn (string $baseline): bool => (float) $baseline < 54));
    }

    public function testTheTextsStandWhereTheMarginsPutThem(): void
    {
        $empty = $this->placed(new LootSheetPdf(new LootPrint(new LootResults(Shop::drawers(), new InMemoryLootDrawRepository(), new LootDrawer())))->render('77'));

        self::assertSame([[54.0, 738.0, 'Loot of order 77'], [54.0, 710.0, 'This order has no loot to draw.']], $empty);

        $draws = new InMemoryLootDrawRepository();
        $order = Shop::order(['shirt' => 1]);
        $commitments = new LootCommitments(Shop::drawers(), Shop::lineOptions(), $draws, new SecureSeeds(), Clock::at());
        $commitments->commit($order);

        $placed = $this->placed(new LootSheetPdf(new LootPrint(new LootResults(Shop::drawers(), $draws, new LootDrawer())))->render('1042'));

        self::assertSame([
            [54.0, 738.0, 'Loot of order 1042'],
            [54.0, 703.0, Shop::line($order, 'shirt')->reference->value().', loot, unit 1'],
            [66.0, 689.0, 'Not drawn yet'],
        ], $placed);
    }

    public function testALongTextIsWrappedToTheWidthLeftByTheMarginsAndTheIndent(): void
    {
        $draws = new InMemoryLootDrawRepository();
        $order = Shop::order(['shirt' => 1]);
        new LootCommitments(Shop::drawers(), Shop::lineOptions(), $draws, new SecureSeeds(), Clock::at())->commit($order);
        $words = 'the quick brown fox jumps over the lazy dog while seventy wizards quietly box the jugs of liquor again and again';
        $long = str_repeat($words.' ', 6);

        $texts = array_column($this->texts(new LootSheetPdf(new LootPrint(new LootResults(Shop::drawers(), $draws, new LootDrawer())), ['draw' => '%s, %s, unit %d '.$long, 'pending' => $long])->render('1042')), 2);

        $heading = Document::wrap(Shop::line($order, 'shirt')->reference->value().', loot, unit 1 '.$long, 504, 10, StandardFont::HelveticaBold);
        $pending = Document::wrap($long, 492, 10, StandardFont::Helvetica);
        self::assertSame(['Loot of order 1042', ...$heading, ...$pending], $texts);
    }

    public function testALineThatLandsOnTheMarginStaysOnItsPage(): void
    {
        $pdf = new LootSheetPdf(new LootPrint(new LootResults(Shop::drawers(), new InMemoryLootDrawRepository(), new LootDrawer())), size: PageSize::of(612, 136))->render('77');

        self::assertSame([[54.0, 82.0, 'Loot of order 77'], [54.0, 54.0, 'This order has no loot to draw.']], $this->placed($pdf));
        self::assertSame(1, preg_match('{/Type /Pages /Kids \[[^]]*\] /Count (\d+)}', $this->inflate($pdf), $count));
        self::assertSame(1, (int) $count[1]);
    }

    /**
     * The texts of the document, in order: where each stands and what it says.
     *
     * @return list<array{float, float, string}>
     */
    private function placed(string $pdf): array
    {
        preg_match_all('/BT \/F\d+ \d+ Tf (\S+) (\S+) Td \(((?:[^)\\\\]|\\\\.)*)\) Tj ET/', $this->inflate($pdf), $matches, \PREG_SET_ORDER);

        return array_map(static fn (array $match): array => [(float) $match[1], (float) $match[2], stripcslashes($match[3])], $matches);
    }

    /**
     * The texts of the document, in order: the font, the size and the text of each.
     *
     * @return list<array{string, string, string}>
     */
    private function texts(string $pdf): array
    {
        preg_match_all('/BT \/(F\d+) (\d+) Tf \S+ \S+ Td \(((?:[^)\\\\]|\\\\.)*)\) Tj ET/', $this->inflate($pdf), $matches, \PREG_SET_ORDER);

        return array_map(static fn (array $match): array => [$match[1], $match[2], stripcslashes($match[3])], $matches);
    }

    private function inflate(string $pdf): string
    {
        $readable = '';
        $at = 0;

        while (1 === preg_match('/\/Filter \/FlateDecode \/Length (\d+) >>\nstream\n/', $pdf, $match, \PREG_OFFSET_CAPTURE, $at)) {
            $start = $match[0][1] + \strlen($match[0][0]);
            $data = substr($pdf, $start, (int) $match[1][0]);
            $readable .= substr($pdf, $at, $start - $at).gzuncompress($data);
            $at = $start + \strlen($data);
        }

        return $readable.substr($pdf, $at);
    }
}
