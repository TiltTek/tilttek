<?php

declare(strict_types=1);

namespace Tilttek\AccessibleDice\Tests\Browser;

use Facebook\WebDriver\JavaScriptExecutor;
use Facebook\WebDriver\WebDriverBy;
use Override;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Panther\Client;
use Symfony\Component\Panther\ProcessManager\WebServerManager;
use Tilttek\AccessibleDice\Tests\Functional\App\TestKernel;
use Twig\Environment;

/**
 * The form in a headless Chrome, its controller started by Stimulus: the expression follows the choice, the buttons
 * step how many dice, a roll of a dice tower on the page is announced in the polite live region, the targets are
 * large, nothing is read aloud by default, and the roll moves only without a request for reduced motion.
 *
 * Run apart, with a chromedriver on the path and Stimulus among the browser dependencies of the PHIL UX kit:
 *
 *     vendor/bin/phpunit --bootstrap tests/bootstrap.php packages/tilttek-accessible-dice/tests/Browser
 */
#[CoversNothing]
#[Group('browser')]
final class AccessibleDiceBrowserTest extends TestCase
{
    private const string HOST = '127.0.0.1';

    private const int PORT = 9183;

    private static ?WebServerManager $server = null;

    /** @var array<string, Client> */
    private static array $browsers = [];

    #[Override]
    public static function setUpBeforeClass(): void
    {
        $stimulus = self::stimulus();

        if (null === $stimulus || !class_exists(Client::class)) {
            return;
        }

        $root = sys_get_temp_dir().'/tilttek-accessible-dice-browser';
        $assets = \dirname(__DIR__, 2).'/assets';

        if (!is_dir($root.'/lib') && !mkdir($root.'/lib', recursive: true) && !is_dir($root.'/lib')) {
            return;
        }

        copy($stimulus, $root.'/stimulus.js');
        copy($assets.'/src/dice_controller.js', $root.'/dice_controller.js');
        copy($assets.'/src/lib/dice.js', $root.'/lib/dice.js');
        copy($assets.'/styles/accessible-dice.css', $root.'/accessible-dice.css');
        file_put_contents($root.'/index.html', self::page());

        self::$server = new WebServerManager($root, self::HOST, self::PORT);
        self::$server->start();
    }

    #[Override]
    public static function tearDownAfterClass(): void
    {
        foreach (self::$browsers as $browser) {
            $browser->quit();
        }

        self::$browsers = [];
        self::$server?->quit();
        self::$server = null;
    }

    #[Override]
    protected function setUp(): void
    {
        if (!self::$server instanceof WebServerManager) {
            self::markTestSkipped('The browser suite needs symfony/panther and Stimulus among the browser dependencies of the PHIL UX kit.');
        }
    }

    public function testTheExpressionFollowsTheChoice(): void
    {
        $browser = $this->open();

        self::assertSame('1d6', $this->read($browser, 'document.querySelector("output").textContent'));
        $browser->findElement(WebDriverBy::cssSelector('input[value="coin"]'))->click();
        $browser->findElement(WebDriverBy::cssSelector('[aria-label="Un de plus"]'))->click();
        $browser->findElement(WebDriverBy::cssSelector('[aria-label="Un de plus"]'))->click();
        self::assertSame('3d{coin}', $this->read($browser, 'document.querySelector("output").textContent'));
        $browser->findElement(WebDriverBy::cssSelector('[aria-label="Un de moins"]'))->click();
        $browser->findElement(WebDriverBy::id('tilttek-accessible-dice-modifier'))->clear()->sendKeys('5');
        self::assertSame('2d{coin}+5', $this->read($browser, 'document.querySelector("output").textContent'));
    }

    public function testARollOfTheTowerIsAnnouncedPolitely(): void
    {
        $browser = $this->open();
        $live = $browser->findElement(WebDriverBy::cssSelector('[role="status"]'));

        self::assertSame('polite', $live->getAttribute('aria-live'));
        self::assertSame('true', $live->getAttribute('aria-atomic'));
        $browser->executeScript(<<<'JS'
            window.dispatchEvent(new CustomEvent('tilttek--dice-tower--tower:drop', {detail: {
                entry: {position: 4, results: [{expression: '2d{coin}', total: 1, output: 1, sets: [{die: 'coin', dice: [{value: 1, face: 'heads', kept: true}, {value: 0, face: 'tails', kept: true}]}]}]},
                view: {name: 'Brom'},
            }}));
            JS);
        $browser->wait(2)->until(static fn (JavaScriptExecutor $client): bool => '' !== $client->executeScript('return document.querySelector("[role=status]").textContent'));

        self::assertSame('Brom a lancé. 2d{coin} : Pile, Face. Total 1.', $this->read($browser, 'document.querySelector("[role=status]").textContent'));
        self::assertFalse($this->read($browser, 'document.querySelector("[data-tilttek--accessible-dice--dice-target=speak]").checked'));
    }

    public function testTheTargetsAreLarge(): void
    {
        $browser = $this->open();

        foreach (['.tilttek-accessible-dice__die', '.tilttek-accessible-dice__number button', '.tilttek-accessible-dice__roll', '.tilttek-accessible-dice__speak'] as $selector) {
            foreach ($browser->findElements(WebDriverBy::cssSelector($selector)) as $target) {
                self::assertGreaterThanOrEqual(44, $target->getSize()->getHeight(), $selector);
                self::assertGreaterThanOrEqual(44, $target->getSize()->getWidth(), $selector);
            }
        }
    }

    public function testTheRollMovesOnlyWithoutAReducedMotion(): void
    {
        self::assertTrue($this->rolls($this->open()));
        self::assertFalse($this->rolls($this->open(reducedMotion: true)));
    }

    private function rolls(Client $browser): bool
    {
        $browser->executeScript('document.querySelector("form").addEventListener("submit", (event) => event.preventDefault())');
        $browser->findElement(WebDriverBy::cssSelector('button[type="submit"]'))->click();

        return (bool) $browser->executeScript('return document.querySelector("form").classList.contains("is-rolling")');
    }

    private function open(bool $reducedMotion = false): Client
    {
        $key = $reducedMotion ? 'reduced' : 'default';
        $arguments = ['--headless', '--disable-gpu', '--window-size=1200,1600', ...($reducedMotion ? ['--force-prefers-reduced-motion'] : [])];
        // A chromedriver of its own for each mode, on a port of the suite, apart from the default one of other suites.
        self::$browsers[$key] ??= Client::createChromeClient(null, $arguments, ['port' => $reducedMotion ? self::PORT + 2 : self::PORT + 1], 'http://'.self::HOST.':'.self::PORT);
        $browser = self::$browsers[$key];
        $browser->request('GET', '/index.html');
        $browser->wait(5)->until(static fn (JavaScriptExecutor $client): bool => true === $client->executeScript('return document.documentElement.dataset.started === "true"'));

        return $browser;
    }

    private function read(Client $browser, string $expression): mixed
    {
        return $browser->executeScript('return '.$expression);
    }

    /**
     * The form as the bundle draws it, in French, on a page that starts its controller.
     */
    private static function page(): string
    {
        $kernel = new TestKernel('test', true);
        $kernel->boot();

        $container = $kernel->getContainer()->get('test.service_container');
        Assert::assertInstanceOf(ContainerInterface::class, $container);
        $twig = $container->get('twig');
        Assert::assertInstanceOf(Environment::class, $twig);
        $form = $twig->createTemplate("{{ accessible_dice('/roll') }}")->render();
        $kernel->shutdown();

        return <<<HTML
            <!doctype html>
            <html lang="fr">
            <head>
            <meta charset="utf-8">
            <title>Accessible dice</title>
            <link rel="stylesheet" href="accessible-dice.css">
            <script type="importmap">{"imports": {"@hotwired/stimulus": "./stimulus.js"}}</script>
            <script type="module">
                import { Application } from '@hotwired/stimulus';
                import Dice from './dice_controller.js';
                Application.start().register('tilttek--accessible-dice--dice', Dice);
                document.documentElement.dataset.started = 'true';
            </script>
            </head>
            <body>{$form}</body>
            </html>
            HTML;
    }

    private static function stimulus(): ?string
    {
        foreach ([\dirname(__DIR__, 4).'/vendor/phil/ux-kit', \dirname(__DIR__, 2).'/vendor/phil/ux-kit'] as $kit) {
            $file = $kit.'/tests/Browser/node_modules/@hotwired/stimulus/dist/stimulus.js';

            if (is_file($file)) {
                return $file;
            }
        }

        return null;
    }
}
