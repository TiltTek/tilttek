<?php

declare(strict_types=1);

namespace Tilttek\Bingo\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\Bingo\Bingo;
use Tilttek\Bingo\CardDealer;
use Tilttek\Bingo\Grid;
use Tilttek\Bingo\Store\InMemoryBingoStore;
use Tilttek\Bingo\Tests\Unit\Fixture\FrozenClock;
use Tilttek\Bingo\Tests\Unit\Fixture\Games;
use Tilttek\Bingo\Tests\Unit\Fixture\RecordingDispatcher;
use Tilttek\Bingo\TickMode;
use Tilttek\Bingo\View\CardView;
use Tilttek\Bingo\View\CellView;
use Tilttek\Bingo\WinPattern;
use Tilttek\Bingo\WinPatterns;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Loader\ChainLoader;
use Twig\Loader\FilesystemLoader;

#[CoversClass(CardView::class)]
#[CoversClass(CellView::class)]
final class CardViewTest extends TestCase
{
    public function testACardIsShownRowByRowWithItsTicks(): void
    {
        [$bingo, $view] = $this->view(won: false);

        self::assertSame('night', $view->game);
        self::assertSame(1, $view->serial);
        self::assertCount(3, $view->rows);
        self::assertCount(3, $view->rows[2]);
        self::assertTrue($view->rows[0][0]->ticked);
        self::assertFalse($view->rows[0][1]->ticked);
        self::assertTrue($view->rows[1][1]->isFree());
        self::assertTrue($view->rows[1][1]->ticked);
        self::assertNull($view->rows[1][1]->label);
        self::assertFalse($view->rows[0][0]->isFree());
        self::assertSame('Square '.substr((string) $view->rows[0][0]->square, 1), $view->rows[0][0]->label);
        self::assertFalse($view->hasWon());
        self::assertNull($view->pattern);
        self::assertInstanceOf(Bingo::class, $bingo);
    }

    public function testTheTemplateSaysEachCellAndTheBlockOfAWin(): void
    {
        [, $view] = $this->view(won: true);
        $html = $this->twig()->render('page.html.twig', ['card' => $view, 'labels' => ['ticked' => 'cochée', 'caption' => 'Carte']]);

        self::assertTrue($view->hasWon());
        self::assertStringContainsString('data-controller="tilttek--bingo--card"', $html);
        self::assertStringContainsString('data-tilttek--bingo--card-game-value="night"', $html);
        self::assertStringContainsString('data-tilttek--bingo--card-serial-value="1"', $html);
        self::assertStringContainsString('data-tilttek--bingo--card-pattern-value="diagonal"', $html);
        self::assertStringContainsString('<caption>Carte <data value="1">1</data></caption>', $html);
        self::assertStringContainsString('<td class="isBingoCell isTicked isFree">Free <span class="isVisuallyHidden">(cochée)</span></td>', $html);
        self::assertSame(3, substr_count($html, '(cochée)'));
        self::assertSame(3, substr_count($html, '<tr>'));
        self::assertSame(9, substr_count($html, '<td'));
        self::assertStringContainsString('<p class="won">Bingo!</p>', $html);
    }

    public function testACardThatDidNotWinHasNoBlockAndEnglishWords(): void
    {
        [, $view] = $this->view(won: false);
        $html = $this->twig()->render('page.html.twig', ['card' => $view]);

        self::assertStringContainsString('data-tilttek--bingo--card-pattern-value=""', $html);
        self::assertStringContainsString('<caption>Bingo card <data value="1">1</data></caption>', $html);
        self::assertStringContainsString('(ticked)', $html);
        self::assertStringContainsString('<td class="isBingoCell">Square ', $html);
        self::assertStringNotContainsString('class="won"', $html);
    }

    /**
     * @return array{Bingo, CardView}
     */
    private function view(bool $won): array
    {
        $grid = new Grid(3);
        $bingo = new Bingo(new InMemoryBingoStore(), new CardDealer(Games::SECRET), new RecordingDispatcher(), new FrozenClock());
        $bingo->create('night', Games::pool(12), $grid, new WinPatterns(WinPattern::diagonal($grid)), TickMode::Called);

        $card = $bingo->deal('night', 'member:1');
        $bingo->call('night', (string) $card->layout[0]);

        if ($won) {
            $bingo->call('night', (string) $card->layout[8]);
            $bingo->claim('night', 1, 'member:1');
        }

        return [$bingo, CardView::of($bingo, $bingo->game('night'), $bingo->card('night', 1))];
    }

    private function twig(): Environment
    {
        $files = new FilesystemLoader();
        $files->addPath(\dirname(__DIR__, 2).'/templates', 'TilttekBingo');

        return new Environment(new ChainLoader([$files, new ArrayLoader([
            'page.html.twig' => "{% extends '@TilttekBingo/card.html.twig' %}{% block won %}<p class=\"won\">Bingo!</p>{% endblock %}",
        ])]), ['strict_variables' => true, 'autoescape' => 'html']);
    }
}
