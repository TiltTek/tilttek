<?php

declare(strict_types=1);

namespace Tilttek\GameShelf\Tests\Unit\Export;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\GameShelf\Copy;
use Tilttek\GameShelf\CopyStatus;
use Tilttek\GameShelf\Export\ShelfCsv;
use Tilttek\GameShelf\Note;
use Tilttek\GameShelf\Shelf;
use Tilttek\GameShelf\Viewer;
use Tilttek\GameShelf\Wish;
use Tilttek\GameShelf\WishKind;
use Tilttek\GameShelf\WishLevel;

#[CoversClass(ShelfCsv::class)]
final class ShelfCsvTest extends TestCase
{
    private function shelf(): Shelf
    {
        return new Shelf(
            'member',
            [
                new Copy('c1', 'harbour', [CopyStatus::Owned, CopyStatus::ForTrade], 'Second "deluxe" edition', 'Attic'),
                new Copy('c2', 'tide', [CopyStatus::Preordered], bringable: false),
            ],
            [
                new Wish('ebb'),
                new Wish('lanterns', [WishKind::WantToPlay, WishKind::Wishlist], WishLevel::MustHave),
            ],
            [
                new Note('harbour', 8, "=HYPERLINK(\"x\")\nsecond line", ['family', '+short'], 12),
                new Note('tide', plays: 1),
            ],
        );
    }

    public function testTheCopiesAreWrittenWithTheirStatuses(): void
    {
        self::assertSame(
            "\"copy\",\"game\",\"statuses\",\"edition\",\"location\",\"bringable\"\r\n"
            ."\"c1\",\"harbour\",\"owned for_trade\",\"Second \"\"deluxe\"\" edition\",\"Attic\",\"yes\"\r\n"
            ."\"c2\",\"tide\",\"preordered\",\"\",\"\",\"no\"\r\n",
            new ShelfCsv()->copies($this->shelf()->view(Viewer::Owner)),
        );
    }

    public function testTheWishlistIsWrittenInItsOrder(): void
    {
        self::assertSame(
            "\"rank\",\"game\",\"kinds\",\"level\"\r\n"
            ."\"1\",\"lanterns\",\"wishlist want_to_play\",\"1\"\r\n"
            ."\"2\",\"ebb\",\"wishlist\",\"\"\r\n",
            new ShelfCsv()->wishlist($this->shelf()->view(Viewer::Owner)),
        );
    }

    public function testACellReadAsAFormulaIsWrittenAsText(): void
    {
        self::assertSame(
            "\"game\",\"rating\",\"plays\",\"tags\",\"comment\"\r\n"
            ."\"harbour\",\"8\",\"12\",\"family; +short\",\"'=HYPERLINK(\"\"x\"\")\nsecond line\"\r\n"
            ."\"tide\",\"\",\"1\",\"\",\"\"\r\n",
            new ShelfCsv()->notes($this->shelf()->view(Viewer::Owner)),
        );
    }

    public function testEveryFormulaStartIsGuarded(): void
    {
        $csv = new ShelfCsv();

        foreach (['=', '+', '-', '@'] as $start) {
            $view = new Shelf('member', [new Copy('c1', 'g', edition: $start.'x')])->view(Viewer::Owner);

            self::assertStringContainsString("\"'".$start.'x"', $csv->copies($view));
        }

        $view = new Shelf('member', notes: [new Note('g', tags: ['-a'])])->view(Viewer::Owner);
        self::assertStringContainsString("\"'-a\"", $csv->notes($view));
    }

    public function testAnotherViewerWritesOnlyWhatTheySee(): void
    {
        $view = $this->shelf()->view(Viewer::Anyone);
        $csv = new ShelfCsv();

        self::assertSame("\"copy\",\"game\",\"statuses\",\"edition\",\"location\",\"bringable\"\r\n", $csv->copies($view));
        self::assertSame("\"rank\",\"game\",\"kinds\",\"level\"\r\n", $csv->wishlist($view));
        self::assertSame("\"game\",\"rating\",\"plays\",\"tags\",\"comment\"\r\n", $csv->notes($view));
    }
}
