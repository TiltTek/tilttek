<?php

declare(strict_types=1);

namespace Tilttek\DiceTower\Tests\Unit\Infrastructure;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Tilttek\DiceTower\Application\TowerTopics;
use Tilttek\DiceTower\Infrastructure\Http\TowerEntriesController;
use Tilttek\DiceTower\Infrastructure\Mercure\TowerSubscriptions;
use Tilttek\DiceTower\Infrastructure\Twig\DiceTowerExtension;
use Tilttek\DiceTower\Infrastructure\Twig\KitComponents;
use Tilttek\DiceTower\Infrastructure\Twig\TowerView;
use Tilttek\DiceTower\Tests\Unit\Fixture\RecordingHub;
use Tilttek\DiceTower\Tests\Unit\Fixture\Tables;
use Tilttek\DiceTower\Tests\Unit\Fixture\Tower;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

#[CoversClass(DiceTowerExtension::class)]
#[CoversClass(KitComponents::class)]
final class DiceTowerExtensionTest extends TestCase
{
    public function testTheViewOfATableAViewerMayWatch(): void
    {
        $tower = new Tower();
        $tower->tower->roll(Tower::table(), '1d6', Tower::member());
        $tower->tower->roll(Tower::table(), '1d{coin}');

        $extension = $this->extension($tower, $this->urls());

        $view = $extension->view('table:7', ['member:1' => 'Aelis']);

        self::assertNotNull($view);
        self::assertSame('table:7', $view['table']);
        self::assertSame('https://hub.test/.well-known/mercure?match=urn%3Atilttek%3Adice-tower%3Atable%253A7', $view['hub']);
        self::assertSame('/dice-tower/table:7/entries', $view['entries_url']);
        self::assertSame(2, $view['last']);
        self::assertSame(['coin' => ['heads' => 'Heads', 'tails' => 'Tails']], $view['faces']);
        self::assertSame(['member:1' => 'Aelis'], $view['names']);
        self::assertSame([1, 2], array_column($view['entries'], 'position'));
        self::assertSame(['Aelis', null], array_column($view['entries'], 'name'));
        self::assertSame($view, $extension->view(Tower::table(), ['member:1' => 'Aelis']));
    }

    public function testAnEmptyTowerWithoutRoute(): void
    {
        $view = $this->extension(new Tower())->view(Tower::table());

        self::assertNotNull($view);
        self::assertSame([0, null, []], [$view['last'], $view['entries_url'], $view['entries']]);
        self::assertSame('/dice-tower/table:7/entries', $this->extension(new Tower(), $this->urls())->view(Tower::table())['entries_url'] ?? null);
        self::assertNull($this->extension(new Tower(), new UrlGenerator(new RouteCollection(), new RequestContext()))->view(Tower::table())['entries_url'] ?? null);
    }

    public function testAViewerWhoMayNotWatchSeesNothing(): void
    {
        $extension = $this->extension(new Tower());
        $twig = new Environment(new ArrayLoader([DiceTowerExtension::TEMPLATE => 'drawn']));

        self::assertNull($extension->view('table:8'));
        self::assertSame('', $extension->tower($twig, 'table:8'));
    }

    public function testTheOverlayIsRenderedWithTheView(): void
    {
        $twig = new Environment(new ArrayLoader([DiceTowerExtension::TEMPLATE => '{{ tower.table }}|{{ title ?? "none" }}|{{ announce ? "log" : "quiet" }}']));
        $extension = $this->extension(new Tower());

        self::assertSame('table:7|none|log', $extension->tower($twig, 'table:7'));
        self::assertSame('table:7|Tower|quiet', $extension->tower($twig, Tower::table(), [], 'Tower', false));
    }

    public function testTheTopicAndTheKit(): void
    {
        $extension = $this->extension(new Tower());

        self::assertSame('urn:tilttek:dice-tower:table%3A7', $extension->topic('table:7'));
        self::assertSame('urn:tilttek:dice-tower:table%3A7', $extension->topic(Tower::table()));
        self::assertSame('Card:Title', $extension->kit('Card:Title'));
        self::assertSame('ui:Card', new KitComponents('/ui/')->name('Card'));
        self::assertSame('ui:kit:Card', new KitComponents('ui/kit')->name('Card'));
        self::assertSame('Card', new KitComponents('/')->name('Card'));
    }

    private function extension(Tower $tower, ?UrlGenerator $urls = null): DiceTowerExtension
    {
        $tables = new Tables('table:7');

        return new DiceTowerExtension($tower->tower, $tables, new TowerTopics(), new TowerSubscriptions($tables, new TowerTopics(), new RecordingHub()), new TowerView(Tower::customDice()), urls: $urls);
    }

    private function urls(): UrlGenerator
    {
        $routes = new RouteCollection();
        $routes->add(TowerEntriesController::ROUTE, new Route('/dice-tower/{table}/entries', requirements: ['table' => TowerEntriesController::TABLE]));

        return new UrlGenerator($routes, new RequestContext());
    }
}
