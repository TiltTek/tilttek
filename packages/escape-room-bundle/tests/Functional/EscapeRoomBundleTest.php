<?php

declare(strict_types=1);

namespace Tilttek\EscapeRoomBundle\Tests\Functional;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Override;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Tilttek\EscapeRoomBundle\Application\GameDesk;
use Tilttek\EscapeRoomBundle\Infrastructure\DependencyInjection\EscapeRoomServices;
use Tilttek\EscapeRoomBundle\Infrastructure\DependencyInjection\KitSubdirectoryPass;
use Tilttek\EscapeRoomBundle\Infrastructure\DependencyInjection\MercureHubPass;
use Tilttek\EscapeRoomBundle\Infrastructure\Doctrine\EscapeRoomTables;
use Tilttek\EscapeRoomBundle\Infrastructure\Http\ConsentController;
use Tilttek\EscapeRoomBundle\Infrastructure\Http\ConsoleController;
use Tilttek\EscapeRoomBundle\Infrastructure\Http\LeaderboardController;
use Tilttek\EscapeRoomBundle\Infrastructure\Http\ScreenController;
use Tilttek\EscapeRoomBundle\Infrastructure\Http\Screens;
use Tilttek\EscapeRoomBundle\Infrastructure\Http\Views;
use Tilttek\EscapeRoomBundle\Infrastructure\Twig\EscapeRoomExtension;
use Tilttek\EscapeRoomBundle\Tests\Functional\App\TestKernel;
use Tilttek\EscapeRoomBundle\Tests\Unit\Fixture\Access;
use Tilttek\EscapeRoomBundle\Tests\Unit\Fixture\RecordingHub;
use Tilttek\EscapeRoomBundle\TilttekEscapeRoomBundle;

/**
 * The bundle in an application: the host drives a game from the console, with and without JavaScript; the hint screen
 * shows the hint, the message and the celebration of an escape; the team agrees to show its result, and the public
 * leaderboard shows it — all through the screens, built of the PHIL UX kit, the games in Doctrine DBAL.
 */
#[CoversClass(TilttekEscapeRoomBundle::class)]
#[CoversClass(EscapeRoomServices::class)]
#[CoversClass(KitSubdirectoryPass::class)]
#[CoversClass(MercureHubPass::class)]
#[CoversClass(Screens::class)]
#[CoversClass(Views::class)]
#[CoversClass(ConsoleController::class)]
#[CoversClass(ScreenController::class)]
#[CoversClass(ConsentController::class)]
#[CoversClass(LeaderboardController::class)]
#[CoversClass(EscapeRoomExtension::class)]
final class EscapeRoomBundleTest extends WebTestCase
{
    private KernelBrowser $client;

    private Access $access;

    private GameDesk $desk;

    protected function setUp(): void
    {
        if (null === TestKernel::kit()) {
            self::markTestSkipped('The PHIL UX kit is not beside the monorepo, nor among the requirements.');
        }

        $this->client = self::createClient();
        $this->client->disableReboot();

        $container = self::getContainer();
        $connection = $container->get('doctrine.dbal.default_connection');
        self::assertInstanceOf(Connection::class, $connection);

        foreach ($connection->getDatabasePlatform()->getCreateTablesSQL(EscapeRoomTables::configure(Schema::editor()->create())->getTables()) as $sql) {
            $connection->executeStatement($sql);
        }

        $access = $container->get(Access::class);
        $desk = $container->get(GameDesk::class);
        self::assertInstanceOf(Access::class, $access);
        self::assertInstanceOf(GameDesk::class, $desk);
        $this->access = $access;
        $this->desk = $desk;
        $this->desk->start('g-1', 'crypt', ResourceReference::of('team', 'owls'));
    }

    public function testTheHostPushesAHintToTheScreenWithoutAScript(): void
    {
        $this->access->host = ResourceReference::of('member', '3');
        $crawler = $this->client->request('GET', '/escape-room/console/g-1');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Console: The crypt');
        self::assertSelectorTextContains('main', 'Push a hint for The candle (2 left)');
        self::assertSelectorTextContains('main', 'locked');
        self::assertStringContainsString('no-store', (string) $this->client->getResponse()->headers->get('Cache-Control'));
        self::assertSame('tilttek--escape-room--console', $crawler->filter('main')->attr('data-controller'));
        self::assertStringStartsWith('https://hub.test/.well-known/mercure?match=', (string) $crawler->filter('main')->attr('data-tilttek--escape-room--console-hub-value'));

        $this->client->submit($crawler->filter('form[action$="/hint"]')->form());
        self::assertResponseRedirects('/escape-room/console/g-1', 303);
        self::assertSame(['hint', 1], [$this->desk->screen('g-1')?->kind->value, $this->desk->screen('g-1')?->tier]);

        $this->client->followRedirect();
        self::assertSelectorTextContains('main', 'Hint for The candle');
        self::assertSelectorTextContains('main', 'Look at the top');
        self::assertSelectorTextContains('main', '2:00 added to the final time');
        self::assertSelectorTextContains('main', 'Push a hint for The candle (1 left)');

        $hub = self::getContainer()->get(HubInterface::class);
        self::assertInstanceOf(RecordingHub::class, $hub);
        self::assertSame(['{"move":"start"}', '{"move":"hint"}'], array_map(static fn (Update $update): string => $update->getData(), $hub->updates));
    }

    public function testTheConsoleAnswersTheScriptInJson(): void
    {
        $this->access->host = ResourceReference::of('member', '3');
        $token = $this->token('/escape-room/console/g-1');

        $this->client->request('POST', '/escape-room/console/g-1/message', ['_token' => $token, 'message' => 'Look behind the clock'], server: ['HTTP_ACCEPT' => 'application/json']);
        self::assertResponseIsSuccessful();
        $body = json_decode((string) $this->client->getResponse()->getContent(), true, 8, \JSON_THROW_ON_ERROR);
        self::assertIsArray($body);
        self::assertSame('message', $body['action']);
        self::assertIsArray($body['game'] ?? null);
        self::assertSame(['kind' => 'message', 'title' => null, 'text' => 'Look behind the clock', 'penalty' => 0], $body['game']['screen'] ?? null);

        $this->client->request('POST', '/escape-room/console/g-1/message', ['_token' => $token, 'message' => ' '], server: ['HTTP_ACCEPT' => 'application/json']);
        self::assertResponseStatusCodeSame(422);
        self::assertSame('{"error":"A message to the hint screen holds 1 to 280 characters."}', $this->client->getResponse()->getContent());

        foreach (['pause', 'resume', 'clear'] as $action) {
            $this->client->request('POST', '/escape-room/console/g-1/'.$action, ['_token' => $token], server: ['HTTP_ACCEPT' => 'application/json']);
            self::assertResponseIsSuccessful();
        }

        $this->client->request('POST', '/escape-room/console/g-1/skip', ['_token' => $token, 'puzzle' => 'candle'], server: ['HTTP_ACCEPT' => 'application/json']);
        self::assertResponseIsSuccessful();
        $this->client->request('POST', '/escape-room/console/g-1/abandon', ['_token' => $token], server: ['HTTP_ACCEPT' => 'application/json']);
        self::assertResponseIsSuccessful();
        self::assertSame('abandoned', $this->desk->game('g-1')->game->outcome?->value);
    }

    public function testARefusedMoveIsShownOnTheConsole(): void
    {
        $this->access->host = ResourceReference::of('member', '3');
        $token = $this->token('/escape-room/console/g-1');

        $this->client->request('POST', '/escape-room/console/g-1/skip', ['_token' => $token, 'puzzle' => 'tomb']);
        self::assertResponseStatusCodeSame(303);
        $this->client->followRedirect();
        self::assertSelectorExists('[role="alert"]');
    }

    public function testTheConsoleIsTheHostsOnly(): void
    {
        $this->client->request('GET', '/escape-room/console/g-1');
        self::assertResponseStatusCodeSame(403);

        $this->client->request('GET', '/escape-room/console/g-404');
        self::assertResponseStatusCodeSame(404);

        $this->access->host = ResourceReference::of('member', '3');
        $this->client->request('POST', '/escape-room/console/g-1/pause', ['_token' => 'forged']);
        self::assertResponseStatusCodeSame(403);
        self::assertFalse($this->desk->game('g-1')->game->paused());
    }

    public function testTheHintScreenShowsTheGameAndItsEscape(): void
    {
        $this->client->request('GET', '/escape-room/screen/g-1');
        self::assertResponseStatusCodeSame(403);

        $this->access->watching = true;
        $crawler = $this->client->request('GET', '/escape-room/screen/g-1');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'The crypt');
        self::assertSame('/escape-room/screen/g-1/state', $crawler->filter('main')->attr('data-tilttek--escape-room--screen-state-url-value'));
        self::assertSame('1:00:00', $crawler->filter('time')->text());
        self::assertSelectorNotExists('form');
        self::assertSelectorExists('[role="status"][aria-live="polite"]');

        $this->desk->pause('g-1', ResourceReference::of('member', '3'));
        $this->client->request('GET', '/escape-room/screen/g-1/state');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.tilttek-escape-room__paused', 'Paused by the host.');
        self::assertSelectorNotExists('h1');

        $this->desk->resume('g-1', ResourceReference::of('member', '3'));
        $clock = self::getContainer()->get('clock');
        self::assertInstanceOf(MockClock::class, $clock);
        $clock->sleep(65);
        $this->desk->guess('g-1', 'candle', 'wick');
        $this->desk->guess('g-1', 'crest', 'lion');
        $this->desk->guess('g-1', 'tomb', '1347');

        $crawler = $this->client->request('GET', '/escape-room/screen/g-1/state');
        self::assertSelectorTextContains('.tilttek-escape-room__outcome', 'Escaped! Final time 1:05, with no hint.');
        self::assertSame('escaped', $crawler->filter('.tilttek-escape-room__state')->attr('data-outcome'));
        self::assertSame('celebration', $crawler->filter('.tilttek-escape-room__celebration')->attr('data-controller'));
        self::assertSame('You escaped in 1:05!', $crawler->filter('.tilttek-escape-room__celebration')->attr('data-celebration-message-value'));
    }

    public function testTheTeamAgreesToShowItsResultOnTheLeaderboard(): void
    {
        $clock = self::getContainer()->get('clock');
        self::assertInstanceOf(MockClock::class, $clock);
        $clock->sleep(70);
        $this->desk->guess('g-1', 'candle', 'wick');
        $this->desk->guess('g-1', 'crest', 'lion');
        $this->desk->guess('g-1', 'tomb', '1347');

        $this->client->request('GET', '/escape-room/leaderboard/crypt');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main', 'No team on the leaderboard yet.');
        self::assertStringContainsString('public', (string) $this->client->getResponse()->headers->get('Cache-Control'));

        $this->client->request('POST', '/escape-room/games/g-1/consent', ['public' => '1', '_token' => 'x']);
        self::assertResponseStatusCodeSame(403);

        $this->access->team = true;
        $this->access->watching = true;

        $crawler = $this->client->request('GET', '/escape-room/screen/g-1');
        self::assertSelectorTextContains('.tilttek-escape-room__consent', 'Your result is not on the public leaderboard.');
        $this->client->submit($crawler->filter('.tilttek-escape-room__consent')->form());
        self::assertResponseRedirects('/escape-room/screen/g-1', 303);
        $this->client->followRedirect();
        self::assertSelectorTextContains('.tilttek-escape-room__consent', 'Take our result off the leaderboard');
        $token = (string) $this->client->getCrawler()->filter('.tilttek-escape-room__consent input[name="_token"]')->attr('value');

        $crawler = $this->client->request('GET', '/escape-room/leaderboard/crypt');
        self::assertSelectorTextContains('caption', 'The teams that escaped The crypt and agreed to show their result, by final time');
        self::assertSame(['1', 'Team 1', '1:10', '0'], $crawler->filter('tbody td')->each(static fn (Crawler $cell): string => $cell->text()));

        $this->client->request('POST', '/escape-room/games/g-1/consent', ['public' => '0', '_token' => $token], server: ['HTTP_ACCEPT' => 'application/json']);
        self::assertSame('{"public":false}', $this->client->getResponse()->getContent());
        $this->client->request('GET', '/escape-room/leaderboard/crypt');
        self::assertSelectorTextContains('main', 'No team on the leaderboard yet.');

        $this->client->request('GET', '/escape-room/leaderboard/vault');
        self::assertResponseStatusCodeSame(404);
    }

    private function token(string $page): string
    {
        return (string) $this->client->request('GET', $page)->filter('input[name="_token"]')->first()->attr('value');
    }

    #[Override]
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }
}
