<?php

declare(strict_types=1);

namespace Tilttek\AdventCalendarBundle\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Tilttek\AdventCalendar\PolicyKind;
use Tilttek\AdventCalendarBundle\Domain\Model\CalendarRecord;
use Tilttek\AdventCalendarBundle\Infrastructure\Console\CheckCalendarsCommand;
use Tilttek\AdventCalendarBundle\Infrastructure\Console\ListCalendarsCommand;
use Tilttek\AdventCalendarBundle\Infrastructure\Console\Policies;
use Tilttek\AdventCalendarBundle\Infrastructure\Console\ShowCalendarCommand;
use Tilttek\AdventCalendarBundle\Infrastructure\DependencyInjection\AdventCalendarServices;
use Tilttek\AdventCalendarBundle\Infrastructure\DependencyInjection\KitSubdirectoryPass;
use Tilttek\AdventCalendarBundle\Infrastructure\Form\CalendarType;
use Tilttek\AdventCalendarBundle\Infrastructure\Form\SurpriseType;
use Tilttek\AdventCalendarBundle\Infrastructure\Http\CalendarController;
use Tilttek\AdventCalendarBundle\Infrastructure\Twig\AdventCalendarExtension;
use Tilttek\AdventCalendarBundle\Infrastructure\Twig\KitComponents;
use Tilttek\AdventCalendarBundle\Tests\Functional\App\TestKernel;
use Tilttek\AdventCalendarBundle\Tests\Unit\Fixture\Calendars;
use Tilttek\AdventCalendarBundle\Tests\Unit\Fixture\GrantedSurprises;
use Tilttek\AdventCalendarBundle\TilttekAdventCalendarBundle;

/**
 * The bundle in an application: a visitor sees the boxes and opens none; a member opens a box with and without
 * JavaScript, is granted its surprise once and celebrated once; a box that may not be opened is refused with the
 * reason; the site edits a calendar with its form and the commands list, show and check the calendars — all through the
 * screen built of the PHIL UX kit, on the 3rd of December 2026 in Toronto.
 */
#[CoversClass(TilttekAdventCalendarBundle::class)]
#[CoversClass(AdventCalendarServices::class)]
#[CoversClass(KitSubdirectoryPass::class)]
#[CoversClass(KitComponents::class)]
#[CoversClass(AdventCalendarExtension::class)]
#[CoversClass(CalendarController::class)]
#[CoversClass(CalendarType::class)]
#[CoversClass(SurpriseType::class)]
#[CoversClass(ListCalendarsCommand::class)]
#[CoversClass(ShowCalendarCommand::class)]
#[CoversClass(CheckCalendarsCommand::class)]
#[CoversClass(Policies::class)]
final class AdventCalendarBundleTest extends WebTestCase
{
    private const string ANN = 'member:44';

    private KernelBrowser $client;

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
            self::markTestSkipped('The PHIL UX kit is not installed: the screen has no recipes to draw with.');
        }

        $this->boot();
    }

    public function testAVisitorSeesTheBoxesAndNoSurprise(): void
    {
        $this->client->request('GET', '/advent-calendar/advent-2026');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('no-store', (string) $this->client->getResponse()->headers->get('Cache-Control'));
        self::assertStringContainsString('private', (string) $this->client->getResponse()->headers->get('Cache-Control'));
        self::assertSelectorTextSame('h1', 'Advent 2026');
        self::assertSelectorTextContains('.tilttek-advent-calendar__policy', 'A box opens on its day and stays openable until December 4, 2026: a missed day is not lost.');
        self::assertSelectorTextContains('.tilttek-advent-calendar__visitor', 'Sign in to open the boxes');
        self::assertSelectorCount(4, 'ol.tilttek-advent-calendar__boxes > li');
        self::assertSelectorExists('ol[data-controller="tilttek--advent-calendar--boxes"][data-tilttek--advent-calendar--boxes-effect-value="fireworks"]');
        self::assertSelectorExists('p[role="status"][aria-live="polite"][data-tilttek--advent-calendar--boxes-target="status"]');
        self::assertSelectorExists('#tilttek-advent-calendar-box-1[data-state="openable"]');
        self::assertSelectorTextContains('#tilttek-advent-calendar-box-1', 'Day 1');
        self::assertSelectorTextContains('#tilttek-advent-calendar-box-1', 'December 1, 2026');
        self::assertSelectorTextContains('#tilttek-advent-calendar-box-1', 'Ready to open.');
        self::assertSelectorExists('#tilttek-advent-calendar-box-4[data-state="locked"]');
        self::assertSelectorTextContains('#tilttek-advent-calendar-box-4', 'Locked');
        self::assertSelectorTextContains('#tilttek-advent-calendar-box-4', 'Opens on December 4, 2026.');
        self::assertSelectorNotExists('form[data-tilttek-advent-calendar-open]');
        self::assertSelectorNotExists('.tilttek-advent-calendar__surprise');
        self::assertSelectorExists('[data-controller="celebration"][data-celebration-autoplay-value="false"][data-celebration-effect-value="fireworks"]');
        self::assertStringNotContainsString('points', (string) $this->client->getResponse()->getContent());
    }

    public function testAnUnknownOrUnpublishedCalendarIsNotFound(): void
    {
        $this->client->request('GET', '/advent-calendar/summer');
        self::assertResponseStatusCodeSame(404);

        $this->client->request('GET', '/advent-calendar/draft');
        self::assertResponseStatusCodeSame(404);

        $this->client->request('GET', '/advent-calendar/Not%20one');
        self::assertResponseStatusCodeSame(404);
    }

    public function testAMemberOpensABoxWithoutJavaScriptAndIsCelebratedOnce(): void
    {
        $crawler = $this->client->request('GET', '/advent-calendar/advent-2026', server: ['HTTP_X_MEMBER' => self::ANN]);

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('.tilttek-advent-calendar__visitor');
        self::assertSelectorCount(3, 'form[data-tilttek-advent-calendar-open]');
        self::assertSelectorTextContains('#tilttek-advent-calendar-box-2 button[type="submit"]', 'Open box 2');

        $form = $crawler->filter('#tilttek-advent-calendar-box-2 form')->form();
        $this->client->submit($form, [], ['HTTP_X_MEMBER' => self::ANN]);

        self::assertResponseStatusCodeSame(303);
        self::assertResponseRedirects('/advent-calendar/advent-2026?opened=2#tilttek-advent-calendar-box-2');

        $this->client->request('GET', '/advent-calendar/advent-2026?opened=2', server: ['HTTP_X_MEMBER' => self::ANN]);

        self::assertSelectorExists('#tilttek-advent-calendar-box-2[data-state="opened"]');
        self::assertSelectorTextContains('#tilttek-advent-calendar-box-2 .tilttek-advent-calendar__surprise', '20 points');
        self::assertSelectorExists('[data-controller="celebration"][data-celebration-autoplay-value="true"][data-celebration-message-value="Box 2 opened!"]');
        self::assertSelectorCount(2, 'form[data-tilttek-advent-calendar-open]');
        self::assertSame(['advent:advent-2026:member:44:2'], $this->granted()->granted);

        $this->client->submit($form, [], ['HTTP_X_MEMBER' => self::ANN]);

        self::assertResponseRedirects('/advent-calendar/advent-2026#tilttek-advent-calendar-box-2');
        self::assertSame(['advent:advent-2026:member:44:2'], $this->granted()->granted);

        $this->client->request('GET', '/advent-calendar/advent-2026?opened=3', server: ['HTTP_X_MEMBER' => self::ANN]);

        self::assertSelectorExists('[data-controller="celebration"][data-celebration-autoplay-value="false"][data-celebration-message-value="Box opened!"]');
    }

    public function testAMemberOpensABoxInTheBackground(): void
    {
        $this->open(3);

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('no-store', (string) $this->client->getResponse()->headers->get('Cache-Control'));
        $answer = $this->json();
        self::assertSame(3, $answer['day']);
        self::assertTrue($answer['first']);
        self::assertTrue($answer['celebrate']);
        self::assertIsString($answer['html']);
        self::assertStringContainsString('id="tilttek-advent-calendar-box-3"', $answer['html']);
        self::assertStringContainsString('data-state="opened"', $answer['html']);
        self::assertStringContainsString('A code from gifts', $answer['html']);

        $this->open(3);

        $again = $this->json();
        self::assertFalse($again['first']);
        self::assertFalse($again['celebrate']);
        self::assertSame(['advent:advent-2026:member:44:3'], $this->granted()->granted);
    }

    public function testABoxThatMayNotBeOpenedNowIsRefusedWithTheReason(): void
    {
        $this->open(4, json: false);

        self::assertResponseStatusCodeSame(409);
        self::assertSelectorTextContains('#tilttek-advent-calendar-refused', 'Box 4 cannot be opened now');
        self::assertSelectorTextContains('#tilttek-advent-calendar-refused', 'It opens on December 4, 2026.');
        self::assertSelectorExists('#tilttek-advent-calendar-box-4[data-state="locked"]');

        $this->open(4);

        self::assertResponseStatusCodeSame(409);
        $answer = $this->json();
        self::assertSame(4, $answer['day']);
        self::assertSame('locked', $answer['state']);
        self::assertIsString($answer['error']);
        self::assertNotSame('', $answer['error']);
        self::assertSame([], $this->granted()->granted);
    }

    public function testABoxOfASameDayCalendarIsMissedOnceItsDayHasPassed(): void
    {
        $this->client->request('GET', '/advent-calendar/same-day', server: ['HTTP_X_MEMBER' => self::ANN]);

        self::assertSelectorTextSame('h1', 'Same day');
        self::assertSelectorTextContains('.tilttek-advent-calendar__policy', 'A box opens on its day only.');
        self::assertSelectorExists('#tilttek-advent-calendar-box-1[data-state="missed"]');
        self::assertSelectorTextContains('#tilttek-advent-calendar-box-1', 'Missed');
        self::assertSelectorTextContains('#tilttek-advent-calendar-box-1', 'Could be opened until December 1, 2026.');
        self::assertSelectorExists('#tilttek-advent-calendar-box-3[data-state="openable"]');

        $this->open(1, json: false, calendar: 'same-day');

        self::assertResponseStatusCodeSame(409);
        self::assertSelectorTextContains('#tilttek-advent-calendar-refused', 'It could be opened until December 1, 2026.');
    }

    public function testOnlyAMemberWithAValidTokenOpensABoxThatExists(): void
    {
        $this->client->request('POST', '/advent-calendar/advent-2026/boxes/1', ['_token' => $this->token()]);
        self::assertResponseStatusCodeSame(403);

        $this->client->request('POST', '/advent-calendar/advent-2026/boxes/1', ['_token' => 'forged'], server: ['HTTP_X_MEMBER' => self::ANN]);
        self::assertResponseStatusCodeSame(403);

        $this->client->request('POST', '/advent-calendar/advent-2026/boxes/9', ['_token' => $this->token()], server: ['HTTP_X_MEMBER' => self::ANN]);
        self::assertResponseStatusCodeSame(404);

        $this->client->request('POST', '/advent-calendar/advent-2026/boxes/0', ['_token' => $this->token()], server: ['HTTP_X_MEMBER' => self::ANN]);
        self::assertResponseStatusCodeSame(404);

        $this->client->request('POST', '/advent-calendar/summer/boxes/1', ['_token' => $this->token()], server: ['HTTP_X_MEMBER' => self::ANN]);
        self::assertResponseStatusCodeSame(404);

        $this->client->request('GET', '/advent-calendar/advent-2026/boxes/1', server: ['HTTP_X_MEMBER' => self::ANN]);
        self::assertResponseStatusCodeSame(405);

        $this->client->request('POST', '/advent-calendar/advent-2026/boxes/1', ['_token' => $this->token()], server: ['HTTP_X_MEMBER' => 'member:ann@example.com']);
        self::assertResponseStatusCodeSame(500);
        self::assertSame([], $this->granted()->granted);
    }

    public function testWithoutTheCelebrationTheScreenPlaysNone(): void
    {
        $this->boot('plain');

        $this->client->request('GET', '/advent-calendar/advent-2026', server: ['HTTP_X_MEMBER' => self::ANN]);
        self::assertSelectorNotExists('[data-controller="celebration"]');
        self::assertSelectorExists('ol[data-tilttek--advent-calendar--boxes-effect-value=""]');

        $this->open(1);
        self::assertTrue($this->json()['first']);
        self::assertFalse($this->json()['celebrate']);

        $this->client->request('GET', '/advent-calendar/advent-2026?opened=1', server: ['HTTP_X_MEMBER' => self::ANN]);
        self::assertSelectorNotExists('[data-controller="celebration"]');
    }

    public function testACalendarOfTheDatabaseIsShownUnlessTheConfigurationHidesIt(): void
    {
        $entityManager = $this->entityManager();
        $entityManager->persist(CalendarRecord::fromCalendar(Calendars::advent(id: 'winter'), 'Winter boxes'));
        $entityManager->persist(CalendarRecord::fromCalendar(Calendars::advent(), 'Hidden by the configuration'));
        $entityManager->flush();

        $this->client->request('GET', '/advent-calendar/winter');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextSame('h1', 'Winter boxes');

        $this->client->request('GET', '/advent-calendar/advent-2026');
        self::assertSelectorTextSame('h1', 'Advent 2026');
    }

    public function testTheSiteEditsACalendarWithItsForm(): void
    {
        $formFactory = self::getContainer()->get('test.form.factory');
        self::assertInstanceOf(FormFactoryInterface::class, $formFactory);

        $record = new CalendarRecord();
        $form = $formFactory->create(CalendarType::class, $record, ['csrf_protection' => false]);
        $form->submit([
            'id' => 'winter',
            'title' => 'Winter boxes',
            'timezone' => 'Europe/Paris',
            'firstDate' => '2026-12-01',
            'closingDate' => '2026-12-10',
            'policy' => 'catch_up',
            'catchUpDays' => '2',
            'published' => '1',
            'surprises' => [3 => ['kind' => 'points', 'reference' => 'stars', 'quantity' => '5'], 7 => ['kind' => 'badge', 'reference' => 'winter', 'quantity' => '1']],
        ]);

        self::assertTrue($form->isValid(), (string) $form->getErrors(true));
        self::assertSame('winter', $record->id);
        self::assertSame('Europe/Paris', $record->timezone);
        self::assertSame('2026-12-10', $record->closingDate);
        self::assertSame(PolicyKind::CatchUp, $record->policy);
        self::assertSame(2, $record->catchUpDays);
        self::assertTrue($record->published);
        self::assertSame([['kind' => 'points', 'reference' => 'stars', 'quantity' => 5], ['kind' => 'badge', 'reference' => 'winter', 'quantity' => 1]], $record->surprises);
        self::assertSame(2, $record->toCalendar()->days());

        $broken = $formFactory->create(CalendarType::class, new CalendarRecord(), ['csrf_protection' => false]);
        $broken->submit(['id' => 'broken', 'title' => 'Broken', 'timezone' => 'UTC', 'firstDate' => '2026-12-01', 'closingDate' => '2026-11-01', 'policy' => 'until_closing', 'surprises' => [['kind' => 'points', 'reference' => 'points', 'quantity' => '1']]]);

        self::assertFalse($broken->isValid());
        self::assertStringContainsString('A calendar closes on the date of its last box', $broken->getErrors()->current()->getMessage());

        $invalid = $formFactory->create(CalendarType::class, new CalendarRecord(), ['csrf_protection' => false]);
        $invalid->submit(['id' => 'x', 'title' => 'X', 'timezone' => 'UTC', 'firstDate' => 'not a date', 'policy' => 'until_closing', 'surprises' => []]);

        self::assertFalse($invalid->isValid());
        self::assertCount(0, $invalid->getErrors());

        $existing = $formFactory->create(CalendarType::class, CalendarRecord::fromCalendar(Calendars::advent(), 'Advent'), ['existing' => true]);
        self::assertTrue($existing->get('id')->isDisabled());
        self::assertFalse($formFactory->create(CalendarType::class)->get('id')->isDisabled());

        $surprise = $formFactory->create(SurpriseType::class);
        $surprise->submit([]);
        self::assertSame(['kind' => 'points', 'reference' => 'points', 'quantity' => 1], $surprise->getData());

        $missing = $formFactory->create(CalendarType::class, new CalendarRecord(), ['csrf_protection' => false]);
        $missing->submit(['id' => 'missing', 'title' => '', 'timezone' => 'UTC', 'policy' => 'until_closing', 'surprises' => [[]]]);

        self::assertFalse($missing->isValid());
    }

    public function testTheCommandsListShowAndCheckTheCalendars(): void
    {
        $application = new Application($this->kernel());

        $list = new CommandTester($application->find('tilttek:advent-calendar:list'));
        self::assertSame(0, $list->execute([]));
        self::assertStringContainsString('advent-2026', $list->getDisplay());
        self::assertStringContainsString('openable until closing', $list->getDisplay());
        self::assertStringContainsString('openable on its day only', $list->getDisplay());
        self::assertStringNotContainsString('draft', $list->getDisplay());

        $show = new CommandTester($application->find('tilttek:advent-calendar:show'));
        self::assertSame(0, $show->execute(['calendar' => 'advent-2026']));
        self::assertStringContainsString('America/Toronto, openable until closing; closes at the end of 2026-12-04.', $show->getDisplay());
        self::assertMatchesRegularExpression('/ 3 +2026-12-03 +2026-12-03 +2026-12-04 +openable +code gifts × 1 /', $show->getDisplay());
        self::assertMatchesRegularExpression('/ 4 +2026-12-04 +2026-12-04 +2026-12-04 +locked +badge advent-week × 1 /', $show->getDisplay());
        self::assertSame(1, $show->execute(['calendar' => 'summer']));
        self::assertStringContainsString('No published calendar "summer".', $show->getDisplay());

        $check = new CommandTester($application->find('tilttek:advent-calendar:check'));
        self::assertSame(0, $check->execute([]));
        self::assertStringContainsString('0 calendars of the database hold together.', $check->getDisplay());

        $entityManager = $this->entityManager();
        $broken = CalendarRecord::fromCalendar(Calendars::advent(id: 'broken'), 'Broken', false);
        $broken->timezone = 'Mars/Olympus';

        $entityManager->persist($broken);
        $entityManager->persist(CalendarRecord::fromCalendar(Calendars::advent(id: 'winter'), 'Winter'));
        $entityManager->flush();

        self::assertSame(1, $check->execute([]));
        self::assertMatchesRegularExpression('/broken +no +"Mars\/Olympus" is not a timezone\./', $check->getDisplay());
        self::assertStringContainsString('1 of 2 calendars of the database do not hold together.', $check->getDisplay());
    }

    private function boot(string $environment = 'test'): void
    {
        self::ensureKernelShutdown();
        $this->client = self::createClient(['environment' => $environment]);
        $this->client->disableReboot();

        $entityManager = $this->entityManager();
        new SchemaTool($entityManager)->createSchema($entityManager->getMetadataFactory()->getAllMetadata());
    }

    private function open(int $day, bool $json = true, string $calendar = 'advent-2026'): void
    {
        $this->client->request('POST', \sprintf('/advent-calendar/%s/boxes/%d', $calendar, $day), ['_token' => $this->token()], server: ['HTTP_X_MEMBER' => self::ANN] + ($json ? ['HTTP_ACCEPT' => 'application/json'] : []));
    }

    private function token(): string
    {
        $crawler = $this->client->request('GET', '/advent-calendar/advent-2026', server: ['HTTP_X_MEMBER' => 'member:token']);
        $token = $crawler->filter('input[name="_token"]')->first()->attr('value');
        self::assertIsString($token);

        return $token;
    }

    /**
     * @return array<array-key, mixed>
     */
    private function json(): array
    {
        $answer = json_decode((string) $this->client->getResponse()->getContent(), true, 8, \JSON_THROW_ON_ERROR);
        self::assertIsArray($answer);

        return $answer;
    }

    private function granted(): GrantedSurprises
    {
        $granted = self::getContainer()->get(GrantedSurprises::class);
        self::assertInstanceOf(GrantedSurprises::class, $granted);

        return $granted;
    }

    private function entityManager(): EntityManagerInterface
    {
        $entityManager = self::getContainer()->get('doctrine.orm.default_entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);

        return $entityManager;
    }

    private function kernel(): KernelInterface
    {
        return $this->client->getKernel();
    }
}
