<?php

declare(strict_types=1);

namespace Tilttek\TableSafetyBundle\Tests\Functional;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Override;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\Field\ChoiceFormField;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Mercure\HubInterface;
use Tilttek\TableSafety\Boundary;
use Tilttek\TableSafety\ConsentChecklist;
use Tilttek\TableSafety\ConsentLevel;
use Tilttek\TableSafety\Feedback\StarsAndWishes;
use Tilttek\TableSafety\LinesAndVeils;
use Tilttek\TableSafety\SafetyTool;
use Tilttek\TableSafety\TableSafety;
use Tilttek\TableSafety\TableSettings;
use Tilttek\TableSafetyBundle\Infrastructure\DependencyInjection\KitSubdirectoryPass;
use Tilttek\TableSafetyBundle\Infrastructure\DependencyInjection\TableSafetyServices;
use Tilttek\TableSafetyBundle\Infrastructure\Http\AnswersController;
use Tilttek\TableSafetyBundle\Infrastructure\Http\CardsController;
use Tilttek\TableSafetyBundle\Infrastructure\Http\GameMasterController;
use Tilttek\TableSafetyBundle\Infrastructure\Http\Screens;
use Tilttek\TableSafetyBundle\Infrastructure\Http\TableController;
use Tilttek\TableSafetyBundle\Infrastructure\Http\Views;
use Tilttek\TableSafetyBundle\Infrastructure\Twig\KitComponents;
use Tilttek\TableSafetyBundle\Infrastructure\Twig\TableSafetyExtension;
use Tilttek\TableSafetyBundle\Tests\Functional\App\TestKernel;
use Tilttek\TableSafetyBundle\Tests\Unit\Fixture\RecordingHub;
use Tilttek\TableSafetyBundle\Tests\Unit\Fixture\Tables;
use Tilttek\TableSafetyBundle\Tests\Unit\Infrastructure\Database;
use Tilttek\TableSafetyBundle\TilttekTableSafetyBundle;
use Twig\Environment;

/**
 * The bundle in an application: a participant opens the table, raises signals with and without JavaScript, answers
 * their lines and veils, checklist, session zero and stars and wishes; the game master sees every signal, marks one as
 * seen, reads what the table agreed and changes its settings — all through the screens, built of the PHIL UX kit.
 */
#[CoversClass(TilttekTableSafetyBundle::class)]
#[CoversClass(TableSafetyServices::class)]
#[CoversClass(KitSubdirectoryPass::class)]
#[CoversClass(KitComponents::class)]
#[CoversClass(TableSafetyExtension::class)]
#[CoversClass(Screens::class)]
#[CoversClass(Views::class)]
#[CoversClass(TableController::class)]
#[CoversClass(AnswersController::class)]
#[CoversClass(GameMasterController::class)]
#[CoversClass(CardsController::class)]
final class TableSafetyBundleTest extends WebTestCase
{
    private KernelBrowser $client;

    private Tables $tables;

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

        if (null === TestKernel::kit()) {
            self::markTestSkipped('The PHIL UX kit is not installed: the screens have no recipes to draw with.');
        }

        $this->client = self::createClient();
        $this->client->disableReboot();

        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        self::assertInstanceOf(Connection::class, $connection);
        Database::create($connection);
        $tables = self::getContainer()->get(Tables::class);
        self::assertInstanceOf(Tables::class, $tables);
        $this->tables = $tables;
    }

    public function testOnlyAParticipantOpensTheTable(): void
    {
        $this->tables->participant = null;
        $this->client->request('GET', '/table-safety/table:7');
        self::assertResponseStatusCodeSame(403);

        $this->client->request('GET', '/table-safety/table:7/game-master');
        self::assertResponseStatusCodeSame(403);

        $this->client->request('GET', '/table-safety/nope');
        self::assertResponseStatusCodeSame(404);

        $this->tables->participant = 'member:ana';
        $this->client->request('GET', '/table-safety/table:8');
        self::assertResponseStatusCodeSame(403);

        $this->client->request('GET', '/table-safety/table:7/game-master');
        self::assertResponseStatusCodeSame(403);
    }

    public function testASignalIsRaisedWithoutJavaScript(): void
    {
        $crawler = $this->client->request('GET', '/table-safety/table:7');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('no-store', (string) $this->client->getResponse()->headers->get('Cache-Control'));
        self::assertSelectorTextContains('h1', 'Table safety');
        self::assertSelectorExists('section[data-controller="tilttek--table-safety--signals"][data-tilttek--table-safety--signals-hub-value="https://hub.test/.well-known/mercure?match=urn%3Atilttek%3Atable-safety%3Atable%253A7"][data-tilttek--table-safety--signals-feed-url-value="/table-safety/table:7/signals"]');
        self::assertSelectorExists('p[role="status"][aria-live="polite"]');
        self::assertSelectorTextContains('section[data-controller] p[data-tilttek--table-safety--signals-target="empty"]', 'No signal yet.');
        self::assertSelectorExists('button.isDanger[type="submit"][aria-describedby="tilttek-table-safety-help-x-card"]');
        self::assertSelectorNotExists('input[name="kind"][value="check-in"]');
        self::assertSelectorNotExists('input[name="kind"][value="all-good"]');
        self::assertSelectorTextContains('.tilttek-table-safety__agreement', 'Not enough answers yet');

        $form = $crawler->filter('form[data-tilttek-table-safety-signal]')->first()->form();
        $this->client->submit($form);

        self::assertResponseRedirects('/table-safety/table:7?raised=x-card', 303);
        $hub = self::getContainer()->get(HubInterface::class);
        self::assertInstanceOf(RecordingHub::class, $hub);
        self::assertCount(1, $hub->updates);
        self::assertSame(['urn:tilttek:table-safety:table%3A7'], $hub->updates[0]->getTopics());
        self::assertStringContainsString('"raised_by":null', $hub->updates[0]->getData());

        $this->client->followRedirect();
        self::assertSelectorTextContains('.tilttek-table-safety__signal-list li strong', 'X-card');
        self::assertSelectorNotExists('.tilttek-table-safety__signal-list form');
    }

    public function testASignalIsRaisedFromAScript(): void
    {
        $token = $this->token();

        $this->client->request('POST', '/table-safety/table:7/signals', ['_token' => $token, 'kind' => 'open-door'], server: ['HTTP_ACCEPT' => 'application/json']);
        self::assertResponseStatusCodeSame(201);
        $body = $this->json();
        self::assertIsArray($body['signal']);
        self::assertSame('open-door', $body['signal']['kind']);
        self::assertSame('game-master', $body['signal']['audience']);
        self::assertNull($body['signal']['raised_by']);

        $this->client->request('GET', '/table-safety/table:7/signals');
        self::assertSame(['signals' => []], $this->json());

        $this->tables->gameMaster = true;
        $this->client->request('GET', '/table-safety/table:7/signals');
        $body = $this->json();
        self::assertIsArray($body['signals']);
        self::assertCount(1, $body['signals']);
        $this->client->request('GET', '/table-safety/table:7/signals?after=2026-11-12T20:00:00%2B00:00');
        self::assertSame(['signals' => []], $this->json());
        $this->client->request('GET', '/table-safety/table:7/signals?after=never');
        self::assertResponseStatusCodeSame(400);
    }

    public function testASignalIsRefused(): void
    {
        $token = $this->token();

        $this->client->request('POST', '/table-safety/table:7/signals', ['_token' => 'forged', 'kind' => 'x-card']);
        self::assertResponseStatusCodeSame(403);

        $this->client->request('POST', '/table-safety/table:7/signals', ['_token' => $token, 'kind' => 'safe-word']);
        self::assertResponseStatusCodeSame(400);

        $this->client->request('POST', '/table-safety/table:7/signals', ['_token' => $token, 'kind' => 'check-in']);
        self::assertResponseStatusCodeSame(403);

        $this->safety()->configure(ResourceReference::of('table', '7'), TableSettings::defaults()->withTools([]));
        $this->client->request('POST', '/table-safety/table:7/signals', ['_token' => $token, 'kind' => 'x-card']);
        self::assertResponseStatusCodeSame(400);

        $this->client->request('GET', '/table-safety/table:7/session-zero');
        self::assertResponseStatusCodeSame(404);
        $this->client->request('GET', '/table-safety/table:7/feedback/s1');
        self::assertResponseStatusCodeSame(404);
        $this->client->request('GET', '/table-safety/table:7/answers');
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('form');
    }

    public function testAParticipantAnswersAndTheGameMasterSeesTheAgreement(): void
    {
        $crawler = $this->client->request('GET', '/table-safety/table:7/answers');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main', 'Your answers are seen by nobody.');

        $form = $crawler->filter('form[name="lines_and_veils"]')->form();
        $form['lines_and_veils[violence][gore]'] = 'line';
        $form['lines_and_veils[phobias][spiders]'] = 'veil';
        $this->client->submit($form);
        self::assertResponseRedirects('/table-safety/table:7/answers?saved=1', 303);

        $crawler = $this->client->followRedirect();
        self::assertSelectorTextContains('[data-slot="alert"]', 'Saved.');
        $this->assertSame('line', $crawler->filter('input[name="lines_and_veils[violence][gore]"][checked]')->attr('value'));
        $form = $crawler->filter('form[name="consent"]')->form();
        $form['consent[violence][torture]'] = 'red';
        $this->client->submit($form);
        self::assertResponseRedirects('/table-safety/table:7/answers?saved=1', 303);

        $crawler = $this->client->request('GET', '/table-safety/table:7/session-zero');
        self::assertSelectorTextContains('.tilttek-table-safety__safety-help', 'Keep what must stay out of the game for your lines and veils and your consent checklist: the game master sees them from your first answer');
        self::assertSelectorExists('.tilttek-table-safety__safety-help a[href="/table-safety/table:7/answers"]');
        $form = $crawler->filter('form[name="session_zero"]')->form();
        $form['session_zero[tone]'] = 'dark';
        $form['session_zero[comfort]'] = 'Breaks';
        $this->client->submit($form);
        self::assertResponseRedirects('/table-safety/table:7/session-zero?saved=1', 303);

        $crawler = $this->client->request('GET', '/table-safety/table:7/feedback/s1');
        self::assertSelectorTextContains('h1', 'Stars and wishes: s1');
        $form = $crawler->filter('form[name="stars_and_wishes"]')->form();
        $form['stars_and_wishes[stars]'] = "The heist\nThe bard";
        $form['stars_and_wishes[wishes]'] = 'More downtime';
        $this->client->submit($form);
        self::assertResponseRedirects('/table-safety/table:7/feedback/s1?saved=1', 303);

        // A player sees the themes only in whole groups of 3 participants.
        $crawler = $this->client->request('GET', '/table-safety/table:7');
        self::assertSelectorTextContains('.tilttek-table-safety__agreement', 'Not enough answers yet: the themes are shown in groups of 3 participants');
        self::assertStringNotContainsString('Gore', $crawler->filter('.tilttek-table-safety__agreement')->text());
        self::assertStringNotContainsString('member:ana', (string) $this->client->getResponse()->getContent());

        // The game master sees the protective list from the first answer, without its author.
        $this->tables->gameMaster = true;
        $this->client->request('GET', '/table-safety/table:7');
        self::assertSelectorTextContains('.tilttek-table-safety__agreement', 'Every answer counts here from the first one, without saying who gave it. The players see these themes in groups of 3 participants.');
        self::assertSelectorTextContains('.tilttek-table-safety__agreement', 'Gore');
        self::assertSelectorTextContains('.tilttek-table-safety__agreement', 'Spiders');
        self::assertSelectorTextContains('.tilttek-table-safety__agreement', 'Torture');
        self::assertStringNotContainsString('member:ana', (string) $this->client->getResponse()->getContent());

        $this->client->request('GET', '/table-safety/table:7/game-master?session=s1');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.tilttek-table-safety__agreement', 'Every answer counts here from the first one');
        self::assertSelectorTextContains('.tilttek-table-safety__agreement', 'Gore');
        $html = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('What tone do you want for the game?', $html);
        self::assertStringContainsString('Dark: 0', $html);
        self::assertStringNotContainsString('Breaks', $html);
        self::assertStringContainsString('Not enough answers yet: the stars and wishes are shown in groups of 3 participants', $html);
        self::assertStringNotContainsString('The bard', $html);
        self::assertStringContainsString('No check-in asked yet.', $html);
        self::assertStringNotContainsString('member:ana', $html);

        $safety = self::getContainer()->get(TableSafety::class);
        self::assertInstanceOf(TableSafety::class, $safety);
        $safety->giveFeedback(ResourceReference::of('table', '7'), 's1', ResourceReference::of('member', 'bo'), new StarsAndWishes(['Zeppelins']), new DateTimeImmutable());
        $safety->giveFeedback(ResourceReference::of('table', '7'), 's1', ResourceReference::of('member', 'cy'), new StarsAndWishes([], ['A map']), new DateTimeImmutable());
        $safety->answerSessionZero(ResourceReference::of('table', '7'), ResourceReference::of('member', 'bo'), ['tone' => 'dark'], new DateTimeImmutable('2026-11-12 20:01:00 UTC'));
        $safety->answerConsent(ResourceReference::of('table', '7'), ResourceReference::of('member', 'bo'), ConsentChecklist::none()->with('spiders', ConsentLevel::Yellow), new DateTimeImmutable('2026-11-12 20:01:00 UTC'));
        $safety->answerLinesAndVeils(ResourceReference::of('table', '7'), ResourceReference::of('member', 'cy'), LinesAndVeils::none()->with('gore', Boundary::Veil), new DateTimeImmutable('2026-11-12 20:02:00 UTC'));
        $safety->answerSessionZero(ResourceReference::of('table', '7'), ResourceReference::of('member', 'cy'), ['tone' => 'light'], new DateTimeImmutable('2026-11-12 20:02:00 UTC'));

        $this->tables->gameMaster = false;
        $this->client->request('GET', '/table-safety/table:7');
        self::assertSelectorTextContains('.tilttek-table-safety__agreement', '3 participants answered.');
        self::assertSelectorTextContains('.tilttek-table-safety__agreement', 'Gore');
        $this->tables->gameMaster = true;

        $this->client->request('GET', '/table-safety/table:7/game-master?session=s1');
        $html = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('3 participants answered.', $html);
        self::assertStringContainsString('Dark: 2', $html);
        self::assertStringContainsString('Breaks', $html);
        self::assertStringContainsString('The bard', $html);
        self::assertStringContainsString('More downtime', $html);
        self::assertStringContainsString('Zeppelins', $html);
        self::assertStringNotContainsString('Not enough answers', $html);
    }

    public function testAnInvalidAnswerIsShownAgain(): void
    {
        $crawler = $this->client->request('GET', '/table-safety/table:7/feedback/s1');
        $form = $crawler->filter('form[name="stars_and_wishes"]')->form();
        $form['stars_and_wishes[stars]'] = str_repeat('é', 501);
        $this->client->submit($form);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('main', 'Each of the stars has at most 500 characters.');

        $this->client->request('GET', '/table-safety/table:7/feedback/Session');
        self::assertResponseStatusCodeSame(404);
    }

    public function testTheGameMasterRunsTheTable(): void
    {
        $this->tables->gameMaster = true;
        $crawler = $this->client->request('GET', '/table-safety/table:7/game-master');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('section[data-tilttek--table-safety--signals-hub-value$="%2Fgame-master"]');

        $form = $crawler->filter('form[data-tilttek-table-safety-signal]')->reduce(static fn (Crawler $node): bool => 'check-in' === $node->filter('input[name="kind"]')->attr('value'))->form();
        $this->client->submit($form);
        self::assertResponseRedirects('/table-safety/table:7/game-master?raised=check-in', 303);
        $clock = self::getContainer()->get('clock');
        self::assertInstanceOf(MockClock::class, $clock);
        $clock->sleep(1);

        $this->tables->gameMaster = false;
        $crawler = $this->client->request('GET', '/table-safety/table:7');
        $form = $crawler->filter('form[data-tilttek-table-safety-signal]')->reduce(static fn (Crawler $node): bool => 'not-okay' === $node->filter('input[name="kind"]')->attr('value'))->form();
        $this->client->submit($form);
        $this->client->submit($crawler->filter('form[data-tilttek-table-safety-signal]')->first()->form());

        $this->tables->gameMaster = true;
        $crawler = $this->client->request('GET', '/table-safety/table:7/game-master');
        self::assertSelectorTextContains('main', 'Not okay: 1');
        self::assertSelectorTextContains('main', 'Someone asked to slow down or stop.');
        self::assertSelectorTextContains('main', 'game master only');

        $acknowledge = $crawler->filter('form[action*="/acknowledge"]');
        self::assertCount(3, $acknowledge);
        $this->client->submit($acknowledge->first()->form());
        self::assertResponseRedirects('/table-safety/table:7/game-master', 303);
        $this->client->submit($acknowledge->first()->form());
        self::assertResponseStatusCodeSame(404);

        $crawler = $this->client->request('GET', '/table-safety/table:7/game-master');
        self::assertCount(2, $crawler->filter('form[action*="/acknowledge"]'));
        $form = $crawler->filter('form[name="table_settings"]')->form();
        $named = $form['table_settings[named_signals]'];
        self::assertInstanceOf(ChoiceFormField::class, $named);
        $named->tick();
        $form['table_settings[signals_seen_by]'] = 'game-master';
        $form['table_settings[custom_themes]'] = 'Clowns';
        $form['table_settings[template]'] = 'online';
        $this->client->submit($form);
        self::assertResponseRedirects('/table-safety/table:7/game-master?saved=1', 303);

        $settings = $this->safety()->settings(ResourceReference::of('table', '7'));
        self::assertTrue($settings->namedSignals);
        self::assertSame(['table-clowns' => 'Clowns'], $settings->customThemes);
        self::assertSame('online', $settings->sessionZero->key);

        $crawler = $this->client->request('GET', '/table-safety/table:7/game-master');
        $form = $crawler->filter('form[name="table_settings"]')->form();
        $form['table_settings[custom_themes]'] = str_repeat('a', 121);
        $this->client->submit($form);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('main', 'has 1 to 120 characters.');

        $token = $crawler->filter('form[action*="/acknowledge"] input[name="_token"]')->attr('value');
        $this->tables->gameMaster = false;
        $this->client->request('POST', '/table-safety/table:7/signals/'.str_repeat('a', 32).'/acknowledge', ['_token' => $token]);
        self::assertResponseStatusCodeSame(403);
    }

    public function testASignalIsAcknowledgedFromAScript(): void
    {
        $this->client->request('POST', '/table-safety/table:7/signals', ['_token' => $this->token(), 'kind' => 'pause'], server: ['HTTP_ACCEPT' => 'application/json']);
        $body = $this->json();
        self::assertIsArray($body['signal']);
        $id = $body['signal']['id'];
        self::assertIsString($id);

        $this->tables->gameMaster = true;
        $crawler = $this->client->request('GET', '/table-safety/table:7/game-master');
        $token = $crawler->filter('form[action*="/acknowledge"] input[name="_token"]')->attr('value');

        $this->client->request('POST', '/table-safety/table:7/signals/'.$id.'/acknowledge', ['_token' => 'forged'], server: ['HTTP_ACCEPT' => 'application/json']);
        self::assertResponseStatusCodeSame(403);
        $this->client->request('POST', '/table-safety/table:7/signals/'.$id.'/acknowledge', ['_token' => $token], server: ['HTTP_ACCEPT' => 'application/json']);
        self::assertResponseStatusCodeSame(204);
        $hub = self::getContainer()->get(HubInterface::class);
        self::assertInstanceOf(RecordingHub::class, $hub);
        self::assertStringContainsString('"acknowledged_at":"2026-11-12T20:00:00+00:00"', $hub->updates[1]->getData());
    }

    public function testTheCardsArePrintedBlank(): void
    {
        $this->tables->participant = null;
        $this->client->request('GET', '/table-safety/table:7/cards');
        self::assertResponseStatusCodeSame(403);
        $this->client->request('GET', '/table-safety/table:7/cards.pdf');
        self::assertResponseStatusCodeSame(403);

        $this->tables->participant = 'member:ana';
        $this->safety()->configure(ResourceReference::of('table', '7'), new TableSettings([SafetyTool::XCard, SafetyTool::LinesAndVeils, SafetyTool::ConsentChecklist, SafetyTool::OpenDoor], customThemes: ['table-clowns' => 'Clowns']));
        $this->client->request('GET', '/table-safety/table:7/cards');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('no-store', (string) $this->client->getResponse()->headers->get('Cache-Control'));
        self::assertSelectorTextContains('h1', 'Printable cards');
        self::assertSelectorExists('nav a[href="/table-safety/table:7/cards"]');
        self::assertSelectorExists('a[href="/table-safety/table:7/cards.pdf"][type="application/pdf"]');
        self::assertSelectorCount(3, 'section.tilttek-table-safety__card');
        self::assertSelectorTextContains('section.tilttek-table-safety__card--x-card h2', 'X-card');
        self::assertSelectorExists('section.tilttek-table-safety__card--lines-and-veils table thead th:nth-child(3)');
        self::assertSelectorTextContains('section.tilttek-table-safety__card--consent-checklist table thead th:nth-child(4)', 'Red');
        self::assertSelectorTextContains('section.tilttek-table-safety__card--lines-and-veils table:last-of-type caption', "This table's themes");
        self::assertSelectorTextContains('section.tilttek-table-safety__card--lines-and-veils table:last-of-type tbody th', 'Clowns');
        self::assertSelectorExists('span.tilttek-table-safety__box[role="img"][aria-label="Veil"]');
        self::assertSelectorNotExists('section.tilttek-table-safety__card--open-door');
        self::assertSelectorNotExists('script');

        $this->client->request('GET', '/table-safety/table:7/cards.pdf');
        self::assertResponseIsSuccessful();
        $response = $this->client->getResponse();
        self::assertSame('application/pdf', $response->headers->get('Content-Type'));
        self::assertSame('inline; filename="table-safety-cards.pdf"', $response->headers->get('Content-Disposition'));
        self::assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        self::assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        self::assertStringStartsWith('%PDF-', (string) $response->getContent());
        // The X-card, then the catalogue's themes over several pages for each sheet.
        self::assertGreaterThan(3, preg_match_all('~/Type /Page\b~', (string) $response->getContent()));
        self::assertStringContainsString('/Title (Printable cards)', (string) $response->getContent());

        $this->safety()->configure(ResourceReference::of('table', '7'), new TableSettings([SafetyTool::OpenDoor]));
        $this->client->request('GET', '/table-safety/table:7/cards');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main', 'This table uses no tool with a printable card.');
        self::assertSelectorNotExists('a[href$="/cards.pdf"]');
        self::assertSelectorNotExists('section.tilttek-table-safety__card');

        $this->client->request('GET', '/table-safety/table:7/cards.pdf');
        self::assertResponseStatusCodeSame(404);
    }

    public function testTheToolsAreListedInTemplates(): void
    {
        $twig = self::getContainer()->get('twig');
        self::assertInstanceOf(Environment::class, $twig);

        self::assertSame('lines-and-veils x-card stars-and-wishes ui:Card', $twig->createTemplate("{{ table_safety_tools()|filter(t => t.value in ['lines-and-veils', 'x-card', 'stars-and-wishes'])|map(t => t.value)|join(' ') }} {{ table_safety_kit('Card') }}")->render());
    }

    /**
     * @return array<array-key, mixed> the JSON of the last response
     *
     * @phpstan-impure
     */
    private function json(): array
    {
        $body = json_decode((string) $this->client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($body);

        return $body;
    }

    private function safety(): TableSafety
    {
        $safety = self::getContainer()->get(TableSafety::class);
        self::assertInstanceOf(TableSafety::class, $safety);

        return $safety;
    }

    private function token(): string
    {
        $crawler = $this->client->request('GET', '/table-safety/table:7');

        return (string) $crawler->filter('form[data-tilttek-table-safety-signal] input[name="_token"]')->attr('value');
    }
}
