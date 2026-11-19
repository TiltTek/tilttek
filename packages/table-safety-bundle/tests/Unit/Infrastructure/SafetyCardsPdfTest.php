<?php

declare(strict_types=1);

namespace Tilttek\TableSafetyBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Phil\PagedMedia\Pdf\PageSize;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\TableSafety\SafetyTool;
use Tilttek\TableSafetyBundle\Infrastructure\Print\SafetyCardsPdf;

/**
 * The printed cards: a page per card, in their order, the themes by category with a box per answer, a new page under
 * the labels of the boxes when the themes overflow, and nothing printed for a tool without a card.
 */
#[CoversClass(SafetyCardsPdf::class)]
final class SafetyCardsPdfTest extends TestCase
{
    private const array GROUPS = [
        ['category' => 'Violence', 'themes' => ['Gore', 'Torture']],
        ['category' => null, 'themes' => ['Clowns']],
    ];

    public function testEachCardIsAPageInItsOrder(): void
    {
        $pdf = new SafetyCardsPdf()->render([SafetyTool::XCard, SafetyTool::LinesAndVeils, SafetyTool::ConsentChecklist, SafetyTool::OpenDoor], self::GROUPS);

        self::assertStringStartsWith('%PDF-', $pdf);
        self::assertSame(3, $this->pages($pdf));
        $pages = $this->contents($pdf);
        self::assertCount(3, $pages);

        self::assertStringContainsString('(X-card) Tj', $pages[0]);
        self::assertStringContainsString('(Touch or raise this card to take out what just happened. Nobody asks why.) Tj', $pages[0]);
        self::assertStringContainsString('54 234 504 504 re S', $pages[0]);
        self::assertStringContainsString('q 42 w 138 318 m 474 654 l S Q', $pages[0]);
        self::assertStringContainsString('q 42 w 138 654 m 474 318 l S Q', $pages[0]);

        self::assertStringContainsString('(Lines and veils) Tj', $pages[1]);
        self::assertStringContainsString('(Line) Tj', $pages[1]);
        self::assertStringContainsString('(Veil) Tj', $pages[1]);
        self::assertStringNotContainsString('(Green) Tj', $pages[1]);
        self::assertStringContainsString('(Violence) Tj', $pages[1]);
        self::assertStringContainsString('(Gore) Tj', $pages[1]);
        self::assertStringContainsString("(This table's themes) Tj", $pages[1]);
        self::assertStringContainsString('(Clowns) Tj', $pages[1]);
        self::assertSame(3 * 2, substr_count($pages[1], ' 11 11 re S'));

        self::assertStringContainsString('(Consent checklist) Tj', $pages[2]);
        self::assertStringContainsString('(Green) Tj', $pages[2]);
        self::assertStringContainsString('(Yellow) Tj', $pages[2]);
        self::assertStringContainsString('(Red) Tj', $pages[2]);
        self::assertSame(3 * 3, substr_count($pages[2], ' 11 11 re S'));
    }

    public function testTheColumnsStandAtTheRightOfThePage(): void
    {
        $page = $this->contents(new SafetyCardsPdf()->render([SafetyTool::ConsentChecklist], [['category' => 'Violence', 'themes' => ['Gore']]]))[0];

        // Letter is 612 points wide: three columns of 54 points end at the right margin, 558.
        self::assertStringContainsString('BT /F1 10 Tf 54 687 Td (Theme) Tj ET', $page);
        self::assertStringContainsString('q 0.5 w 54 681 m 558 681 l S Q', $page);
        self::assertStringContainsString('q 1 w 417.5 645 11 11 re S Q', $page);
        self::assertStringContainsString('q 1 w 471.5 645 11 11 re S Q', $page);
        self::assertStringContainsString('q 1 w 525.5 645 11 11 re S Q', $page);
        self::assertStringContainsString('BT /F1 10 Tf 461.16 687 Td (Yellow) Tj ET', $page);
        self::assertStringContainsString('BT /F1 11 Tf 54 665 Td (Violence) Tj ET', $page);
        self::assertStringContainsString('BT /F2 10 Tf 65 647 Td (Gore) Tj ET', $page);
    }

    public function testThemesThatOverflowGoOnANewPageUnderTheLabelsAgain(): void
    {
        $themes = array_map(static fn (int $index): string => 'Theme '.$index, range(1, 60));
        $pdf = new SafetyCardsPdf()->render([SafetyTool::LinesAndVeils], [['category' => 'Many', 'themes' => $themes]]);
        $pages = $this->contents($pdf);

        self::assertCount(2, $pages);
        self::assertSame(2, $this->pages($pdf));
        self::assertStringContainsString('(Theme 1) Tj', $pages[0]);
        self::assertStringNotContainsString('(Theme 60) Tj', $pages[0]);
        self::assertStringContainsString('(Theme 60) Tj', $pages[1]);
        self::assertStringContainsString('BT /F1 10 Tf 54 738 Td (Theme) Tj ET', $pages[1]);
        self::assertStringContainsString('(Line) Tj', $pages[1]);
        self::assertStringNotContainsString('(Lines and veils) Tj', $pages[1]);
        self::assertSame(60, preg_match_all('/\(Theme \d+\) Tj/', $pages[0].$pages[1]));

        // The last theme of the first page stands on the bottom margin's side of it, 54 points up, or above.
        self::assertStringContainsString('BT /F2 10 Tf 65 71 Td (Theme 33) Tj ET', $pages[0]);
        self::assertStringContainsString('BT /F2 10 Tf 65 716 Td (Theme 34) Tj ET', $pages[1]);
    }

    public function testACategoryHeadingIsNeverLeftAloneAtTheBottomOfAPage(): void
    {
        $first = array_map(static fn (int $index): string => 'First '.$index, range(1, 32));
        $pages = $this->contents(new SafetyCardsPdf()->render([SafetyTool::LinesAndVeils], [
            ['category' => 'One', 'themes' => $first],
            ['category' => 'Two', 'themes' => ['Second']],
        ]));

        self::assertCount(2, $pages);
        // The last theme of the first category stands at 89; a heading at 71 would leave no room for a theme under it.
        self::assertStringContainsString('BT /F2 10 Tf 65 89 Td (First 32) Tj ET', $pages[0]);
        self::assertStringNotContainsString('(Two) Tj', $pages[0]);
        self::assertStringContainsString('(Two) Tj', $pages[1]);
    }

    public function testTheXCardIsDrawnToTheLetterPageInFull(): void
    {
        $page = $this->contents(new SafetyCardsPdf()->render([SafetyTool::XCard], []))[0];

        self::assertSame([
            'q 4 w 0 G 54 234 504 504 re S Q',
            'q 42 w 138 318 m 474 654 l S Q',
            'q 42 w 138 654 m 474 318 l S Q',
            'BT /F1 18 Tf 54 198 Td (X-card) Tj ET',
            'BT /F2 11 Tf 54 180 Td (Touch or raise this card to take out what just happened. Nobody asks why.) Tj ET',
        ], $this->lines($page));
    }

    public function testTheXCardIsASquareThatFitsALandscapePage(): void
    {
        // 792 by 612: the width leaves 684 points, the height 396 once the room of the text is kept; the square takes the
        // smaller and stands in the middle.
        $page = $this->contents(new SafetyCardsPdf([], PageSize::letter()->landscape())->render([SafetyTool::XCard], []))[0];

        self::assertSame([
            'q 4 w 0 G 198 162 396 396 re S Q',
            'q 33 w 264 228 m 528 492 l S Q',
            'q 33 w 264 492 m 528 228 l S Q',
            'BT /F1 18 Tf 54 126 Td (X-card) Tj ET',
            'BT /F2 11 Tf 54 108 Td (Touch or raise this card to take out what just happened. Nobody asks why.) Tj ET',
        ], $this->lines($page));
    }

    public function testAHelpTextIsWrappedToTheWidthBetweenTheMarginsAndLinesStandFifteenPointsApart(): void
    {
        $help = 'alpha beta gamma delta epsilon zeta eta theta iota kappa lambda mu nu xi omicron pi rho sigma tau upsilon';
        $size = PageSize::of(300, 500);

        $card = $this->contents(new SafetyCardsPdf(['x_card_help' => $help], $size)->render([SafetyTool::XCard], []))[0];
        self::assertSame([
            'BT /F2 11 Tf 54 200 Td (alpha beta gamma delta epsilon zeta) Tj ET',
            'BT /F2 11 Tf 54 185 Td (eta theta iota kappa lambda mu nu xi) Tj ET',
            'BT /F2 11 Tf 54 170 Td (omicron pi rho sigma tau upsilon) Tj ET',
        ], \array_slice($this->lines($card), 4));

        // On a sheet, what follows the help starts one line under its last line.
        $sheet = $this->contents(new SafetyCardsPdf(['lines_and_veils_help' => $help], $size)->render([SafetyTool::LinesAndVeils], [['category' => 'C', 'themes' => ['T']]]))[0];
        self::assertSame([
            'BT /F1 18 Tf 54 446 Td (Lines and veils) Tj ET',
            'BT /F2 11 Tf 54 428 Td (alpha beta gamma delta epsilon zeta) Tj ET',
            'BT /F2 11 Tf 54 413 Td (eta theta iota kappa lambda mu nu xi) Tj ET',
            'BT /F2 11 Tf 54 398 Td (omicron pi rho sigma tau upsilon) Tj ET',
            'BT /F1 10 Tf 54 380 Td (Theme) Tj ET',
        ], \array_slice($this->lines($sheet), 0, 5));
    }

    public function testACategoryHeadingStaysWhenItsFirstThemeFitsUnderIt(): void
    {
        // The heading of the second category stands at 89, 18 points above the room it needs for a theme: both stay.
        $first = array_map(static fn (int $index): string => 'First '.$index, range(1, 31));
        $pages = $this->contents(new SafetyCardsPdf()->render([SafetyTool::LinesAndVeils], [
            ['category' => 'One', 'themes' => $first],
            ['category' => 'Two', 'themes' => ['Second']],
        ]));

        self::assertCount(1, $pages);
        self::assertStringContainsString('BT /F1 11 Tf 54 89 Td (Two) Tj ET', $pages[0]);
        self::assertStringContainsString('BT /F2 10 Tf 65 71 Td (Second) Tj ET', $pages[0]);
    }

    public function testAThemeOnTheBottomMarginStaysOnThePage(): void
    {
        // On a page 253 points high, the fourth theme stands at 54, the margin itself: it still fits.
        $themes = ['One', 'Two', 'Three', 'Four'];
        $pages = $this->contents(new SafetyCardsPdf()->render([SafetyTool::ConsentChecklist], [['category' => 'C', 'themes' => $themes]]));
        self::assertCount(1, $pages);

        $pages = $this->contents(new SafetyCardsPdf([], PageSize::of(612, 253))->render([SafetyTool::ConsentChecklist], [['category' => 'C', 'themes' => $themes]]));

        self::assertCount(1, $pages);
        self::assertStringContainsString('BT /F2 10 Tf 65 54 Td (Four) Tj ET', $pages[0]);
    }

    public function testTheLabelsAndThePageAreTheApplications(): void
    {
        $service = new SafetyCardsPdf(['x_card' => 'Carte X'], PageSize::a4());
        $pdf = $service->render([SafetyTool::XCard], [], ['x_card_help' => 'Levez-la.', 'title' => 'Cartes'], new DateTimeImmutable('2026-11-12T20:00:00+00:00'));
        $page = $this->contents($pdf)[0];

        self::assertStringContainsString('(Carte X) Tj', $page);
        self::assertStringContainsString('(Lev', $page);
        self::assertStringContainsString('/MediaBox [0 0 595 842]', $pdf);
        self::assertStringContainsString('/Title (Cartes)', $pdf);
        self::assertStringContainsString('20261112200000', $pdf);
    }

    public function testATableWithoutAPrintedToolGetsOneBlankPage(): void
    {
        $pdf = new SafetyCardsPdf()->render([SafetyTool::OpenDoor, SafetyTool::StarsAndWishes], self::GROUPS);

        self::assertSame(1, $this->pages($pdf));
        self::assertStringContainsString('/Title (Printable cards)', $pdf);
        self::assertSame([], array_filter($this->contents($pdf), static fn (string $page): bool => '' !== trim($page)));
    }

    /**
     * The operators of a content stream, one per line.
     *
     * @return list<string>
     */
    private function lines(string $content): array
    {
        return array_values(array_filter(explode("\n", $content), static fn (string $line): bool => '' !== $line));
    }

    private function pages(string $pdf): int
    {
        return (int) preg_match_all('~/Type /Page\b~', $pdf);
    }

    /**
     * The content streams of the pages, inflated.
     *
     * @return list<string>
     */
    private function contents(string $pdf): array
    {
        preg_match_all("~<<([^<>]*(?:<<[^<>]*>>[^<>]*)*)>>\\s*stream\r?\n(.*?)\r?\nendstream~s", $pdf, $streams, \PREG_SET_ORDER);
        $pages = [];

        foreach ($streams as [, $dictionary, $body]) {
            if (str_contains($dictionary, '/Subtype') || str_contains($dictionary, '/Length1') || str_contains($dictionary, '/Type')) {
                continue;
            }

            $content = str_contains($dictionary, '/FlateDecode') ? gzuncompress($body) : $body;
            self::assertIsString($content);
            $pages[] = $content;
        }

        return $pages;
    }
}
