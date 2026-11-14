<?php

declare(strict_types=1);

namespace Tilttek\GeneratorBundle\Tests\Functional;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use ReflectionClass;
use ReflectionProperty;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\KernelInterface;
use Tilttek\Generator\Draw;
use Tilttek\Generator\Generator;
use Tilttek\GeneratorBundle\Application\Contract\StateStore;
use Tilttek\GeneratorBundle\Application\MemoryTables;
use Tilttek\GeneratorBundle\Infrastructure\State\CacheStateStore;
use Tilttek\GeneratorBundle\Tests\Fixture\Files;
use Tilttek\GeneratorBundle\Tests\Functional\App\TestKernel;
use Tilttek\GeneratorBundle\TilttekGeneratorBundle;
use Twig\Environment;

/**
 * The bundle in an application: the generators of config/generators read and cached, drawn in a template that draws
 * again, through the API within its rate limit and with a client's token, and checked by the command.
 */
#[CoversClass(TilttekGeneratorBundle::class)]
final class GeneratorBundleTest extends KernelTestCase
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

        if (is_dir(sys_get_temp_dir().'/tilttek-generator-bundle-test')) {
            Files::remove(sys_get_temp_dir().'/tilttek-generator-bundle-test');
        }

        self::bootKernel();
    }

    public function testTheGeneratorsOfTheProjectAreDrawn(): void
    {
        $generator = $this->service(Generator::class, Generator::class);

        self::assertSame(['days', 'name.short', 'inn', 'broken'], array_keys($generator->catalog()->generators()));
        self::assertSame(['name.short'], array_unique([$generator->draw('name.short')->generator]));
        self::assertCount(3, $generator->draw('name.short', new Draw(3))->results);
    }

    /**
     * In a process of its own, so that no template compiled by another test was declared before.
     */
    #[RunInSeparateProcess]
    public function testTheTemplatesAreLoadedFromCompiledFilesNeverByEval(): void
    {
        $generator = $this->service(Generator::class, Generator::class);
        $generator->draw('name.short', new Draw(3));

        $kernel = self::$kernel;
        self::assertInstanceOf(KernelInterface::class, $kernel);
        $producer = new ReflectionProperty(Generator::class, 'producer')->getValue($generator);
        self::assertIsObject($producer);
        $sources = new ReflectionProperty($producer, 'sources')->getValue($producer);
        self::assertIsArray($sources);
        self::assertContains('name.short:template:0', array_keys($sources));

        $directory = realpath($kernel->getCacheDir());
        self::assertIsString($directory);
        $compiled = 0;

        // Twig evaluates a compiled template only when its class is still missing once its file is loaded: the class of
        // every template of this process was declared by a file, those of the generator by files of its directory.
        foreach (get_declared_classes() as $class) {
            if (str_starts_with($class, '__TwigTemplate_')) {
                $file = (string) new ReflectionClass($class)->getFileName();
                self::assertStringNotContainsString('eval()', $file);
                self::assertFileExists($file);
                $compiled += (int) str_starts_with($file, $directory.'/tilttek_generator/twig/');
            }
        }

        self::assertSame(\count($sources), $compiled);
    }

    public function testATemplateDrawsAndDrawsAgain(): void
    {
        $html = $this->service('twig', Environment::class)->render('page.html.twig');

        self::assertStringContainsString('data-controller="tilttek--generator-bundle--generator"', $html);
        self::assertStringContainsString('data-tilttek--generator-bundle--generator-url-value="/api/generators/name.short"', $html);
        self::assertStringContainsString('data-tilttek--generator-bundle--generator-count-value="3"', $html);
        self::assertSame(3 + 1, preg_match_all('/<li>[A-Z][a-z]{3}<\/li>|<li>The [A-Z][a-z]{3}<\/li>/', $html));
        self::assertStringContainsString('>Again</button>', $html);
        self::assertStringContainsString('data-tilttek--generator-bundle--generator-target="attribution">Syllables by the testers (CC0-1.0)</p>', $html);
        self::assertStringContainsString('data-tilttek--generator-bundle--generator-target="attribution">Inns of the tests; Syllables by the testers (CC0-1.0)</p>', $html);
        self::assertStringContainsString('>Another</button>', $html);
    }

    public function testTheApiDrawsWithinItsRateLimit(): void
    {
        $kernel = self::$kernel;
        self::assertInstanceOf(KernelInterface::class, $kernel);

        $first = $kernel->handle(Request::create('/api/generators/name.short?count=2&unique=1', server: ['REMOTE_ADDR' => '192.0.2.7']));
        self::assertSame(200, $first->getStatusCode(), (string) $first->getContent());
        self::assertSame(200, $kernel->handle(Request::create('/api/generators/name.short', server: ['REMOTE_ADDR' => '192.0.2.7']))->getStatusCode());
        $third = $kernel->handle(Request::create('/api/generators/name.short', server: ['REMOTE_ADDR' => '192.0.2.7']));
        self::assertSame(429, $third->getStatusCode());
        self::assertNotNull($third->headers->get('Retry-After'));

        self::assertSame(401, $kernel->handle(Request::create('/api/generators/inn'))->getStatusCode());
        $inn = $kernel->handle(Request::create('/api/generators/inn', server: ['HTTP_AUTHORIZATION' => 'Bearer secret']));
        self::assertSame(200, $inn->getStatusCode());
        self::assertStringContainsString('"staff":[', (string) $inn->getContent());
        self::assertStringContainsString('"attributions":[{"source":"Inns of the tests"},{"licence":"CC0-1.0","attribution":"Syllables by the testers"}]', (string) $inn->getContent());
        self::assertSame(422, $kernel->handle(Request::create('/api/generators/broken', server: ['HTTP_AUTHORIZATION' => 'Bearer secret']))->getStatusCode());
    }

    public function testATableWithAMemoryKeepsItsStateInTheCachePool(): void
    {
        $tables = $this->service(MemoryTables::class, MemoryTables::class);
        $tables->forget('days', 'campaign');

        self::assertSame(['Day 1 of spring', 'Day 2 of spring'], $tables->draw('days', 'campaign', 2)->results);
        self::assertSame(['day' => '2', 'season' => 'spring'], $this->service(StateStore::class, CacheStateStore::class)->load('days', 'campaign'));
        self::assertSame(['Day 3 of spring'], $tables->draw('days', 'campaign')->results);
    }

    public function testTheCommandChecksTheGenerators(): void
    {
        $kernel = self::$kernel;
        self::assertInstanceOf(KernelInterface::class, $kernel);
        $tester = new CommandTester(new Application($kernel)->find('tilttek:generator:validate'));

        self::assertSame(0, $tester->execute([]));
        self::assertStringContainsString('1 lists and 4 generators are valid.', $tester->getDisplay());
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    private function service(string $id, string $class): object
    {
        $service = self::getContainer()->get($id);
        self::assertInstanceOf($class, $service);

        return $service;
    }
}
