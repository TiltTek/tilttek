<?php

declare(strict_types=1);

namespace Tilttek\DiceTower\Tests\Functional;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mercure\HubInterface;
use Tilttek\DiceTower\Application\DiceTower;
use Tilttek\DiceTower\Infrastructure\DependencyInjection\KitSubdirectoryPass;
use Tilttek\DiceTower\Infrastructure\DependencyInjection\TowerServices;
use Tilttek\DiceTower\Infrastructure\Http\TowerEntriesController;
use Tilttek\DiceTower\Infrastructure\Twig\DiceTowerExtension;
use Tilttek\DiceTower\Tests\Functional\App\TestKernel;
use Tilttek\DiceTower\Tests\Unit\Fixture\RecordingHub;
use Tilttek\DiceTower\Tests\Unit\Fixture\Tower;
use Tilttek\DiceTower\TilttekDiceTowerBundle;
use Twig\Environment;

/**
 * The bundle in an application: a roll of the journal of TilttekDiceBundle dropped in a table's tower, pushed to the
 * hub, read again through the route of the entries by a viewer seated at the table, and drawn with the card of the
 * PHIL UX kit, in French, for that viewer only.
 */
#[CoversClass(TilttekDiceTowerBundle::class)]
#[CoversClass(TowerServices::class)]
#[CoversClass(KitSubdirectoryPass::class)]
#[CoversClass(DiceTowerExtension::class)]
#[CoversClass(TowerEntriesController::class)]
final class DiceTowerBundleTest extends KernelTestCase
{
    #[Override]
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    #[Override]
    protected function setUp(): void
    {
        if (!ContainerBuilder::willBeAvailable('symfony/event-dispatcher', EventDispatcher::class, ['symfony/dependency-injection'])) {
            self::markTestSkipped('The event dispatcher is installed for development only: no kernel can boot.');
        }
    }

    public function testARollIsPushedReadAgainAndDrawn(): void
    {
        self::bootKernel();
        $tower = self::getContainer()->get(DiceTower::class);
        self::assertInstanceOf(DiceTower::class, $tower);
        $entry = $tower->roll(Tower::table(), '2d{coin}', Tower::member());
        $hub = self::getContainer()->get(HubInterface::class);
        self::assertInstanceOf(RecordingHub::class, $hub);

        self::assertCount(1, $hub->updates);
        self::assertSame(['urn:tilttek:dice-tower:table%3A7'], $hub->updates[0]->getTopics());
        self::assertTrue($hub->updates[0]->isPrivate());

        $twig = self::getContainer()->get('twig');
        self::assertInstanceOf(Environment::class, $twig);
        $html = $twig->render('page.html.twig');

        self::assertStringContainsString('<section data-slot="card" data-size="default" class="isCard tilttek-dice-tower" aria-labelledby="tilttek-dice-tower-table%3A7" data-controller="tilttek--dice-tower--tower"', $html);
        self::assertStringContainsString('data-tilttek--dice-tower--tower-hub-value="https://hub.test/.well-known/mercure?match=urn%3Atilttek%3Adice-tower%3Atable%253A7"', $html);
        self::assertStringContainsString('data-tilttek--dice-tower--tower-entries-url-value="/dice-tower/table:7/entries"', $html);
        self::assertStringContainsString('data-tilttek--dice-tower--tower-last-value="'.$entry->position.'"', $html);
        self::assertStringContainsString('data-tilttek--dice-tower--tower-faces-value="{&quot;coin&quot;:{&quot;heads&quot;:&quot;Heads&quot;,&quot;tails&quot;:&quot;Tails&quot;}}"', $html);
        self::assertStringContainsString('data-tilttek--dice-tower--tower-names-value="{&quot;member:1&quot;:&quot;Aelis&quot;}"', $html);
        self::assertStringContainsString('data-tilttek--dice-tower--tower-dropped-value="non compté"', $html);
        self::assertStringContainsString('<h3 data-slot="card-title" class="isCardTitle" id="tilttek-dice-tower-table%3A7">Tour de dés</h3>', $html);
        self::assertStringContainsString('<ol class="tilttek-dice-tower__entries" role="log" aria-labelledby="tilttek-dice-tower-table%3A7" data-tilttek--dice-tower--tower-target="entries">', $html);
        self::assertMatchesRegularExpression('{<li class="tilttek-dice-tower__entry" data-position="'.$entry->position.'"><span class="tilttek-dice-tower__roller">Aelis</span>\s*<span class="tilttek-dice-tower__result"><code>2d\{coin\}</code> = <output>[012]</output> <span class="tilttek-dice-tower__dice"><span class="tilttek-dice-tower__die">(Heads|Tails)</span>, <span class="tilttek-dice-tower__die">(Heads|Tails)</span></span>\s*</span></li>}', $html);
        self::assertStringContainsString('<p class="tilttek-dice-tower__empty" data-tilttek--dice-tower--tower-target="empty" hidden>Aucun jet pour l&#039;instant.</p>', $html);
        self::assertSame(1, substr_count($html, 'data-controller="tilttek--dice-tower--tower"'));

        $kernel = self::$kernel;
        self::assertNotNull($kernel);
        $response = $kernel->handle(Request::create('http://localhost/dice-tower/table:7/entries?after=0'));
        self::assertSame(200, $response->getStatusCode());
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($body);
        self::assertIsArray($body['entries']);
        self::assertSame($hub->updates[0]->getData(), json_encode($body['entries'][0], \JSON_THROW_ON_ERROR));
        self::assertSame(403, $kernel->handle(Request::create('http://localhost/dice-tower/table:8/entries'))->getStatusCode());
        self::assertSame(404, $kernel->handle(Request::create('http://localhost/dice-tower/Table/entries'))->getStatusCode());
    }

    public function testAnEmptyQuietTower(): void
    {
        self::bootKernel();
        $twig = self::getContainer()->get('twig');
        self::assertInstanceOf(Environment::class, $twig);
        $html = $twig->createTemplate("{{ dice_tower('table:7', title: 'Table 7', announce: false) }}")->render();

        self::assertStringContainsString('id="tilttek-dice-tower-table%3A7">Table 7</h3>', $html);
        self::assertStringContainsString('<ol class="tilttek-dice-tower__entries" aria-labelledby="tilttek-dice-tower-table%3A7"', $html);
        self::assertStringContainsString('data-tilttek--dice-tower--tower-names-value="{}"', $html);
        self::assertStringContainsString('data-tilttek--dice-tower--tower-target="empty">Aucun jet', $html);
        self::assertStringContainsString('data-tilttek--dice-tower--tower-last-value="0"', $html);
    }
}
