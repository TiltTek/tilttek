<?php

declare(strict_types=1);

namespace Tilttek\TableSafetyBundle\Tests\Functional\App;

use Override;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Tilttek\TableSafetyBundle\Tests\Unit\Fixture\RecordingHub;
use Tilttek\TableSafetyBundle\Tests\Unit\Fixture\Tables;

/**
 * An application as small as the bundle needs: FrameworkBundle with sessions, CSRF and forms, TwigBundle and Twig
 * components, DoctrineBundle on SQLite in memory, the catalogue of phil/content-warnings-bundle, a hub that records
 * what it is given, the test's tables, and the bundle with its routes; the recipes of the PHIL UX kit are copied where
 * `ux:install` would put them, in the subdirectory `ui` of the Toolkit, which its parameter stands for.
 */
final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    /**
     * The recipes of the kit the screens are built of.
     */
    private const array RECIPES = [
        'alert/templates/components/Alert',
        'alert/templates/components/Alert/Title',
        'badge/templates/components/Badge',
        'button/templates/components/Button',
        'card/templates/components/Card',
        'card/templates/components/Card/Header',
        'card/templates/components/Card/Title',
        'card/templates/components/Card/Description',
        'card/templates/components/Card/Content',
    ];

    /**
     * The PHIL UX kit in a PHIL monorepo beside the Tilttek one, or among the development requirements; null when none
     * has it.
     */
    public static function kit(): ?string
    {
        foreach (['phil', 'phil-prerelease'] as $monorepo) {
            if (is_file($kit = __DIR__.'/../../../../../../../Phil/'.$monorepo.'/packages/ux-kit/manifest.json')) {
                return \dirname($kit);
            }
        }

        foreach ([__DIR__.'/../../../../../vendor/phil/ux-kit', __DIR__.'/../../../vendor/phil/ux-kit'] as $kit) {
            if (is_file($kit.'/manifest.json')) {
                return $kit;
            }
        }

        return null;
    }

    #[Override]
    public function getProjectDir(): string
    {
        return __DIR__;
    }

    #[Override]
    public function getCacheDir(): string
    {
        return $this->root().'/cache/'.$this->environment;
    }

    #[Override]
    public function getLogDir(): string
    {
        return $this->root().'/log';
    }

    private function root(): string
    {
        return sys_get_temp_dir().'/tilttek-table-safety-bundle-test/'.hash('xxh128', __DIR__);
    }

    /**
     * The templates of the recipes, where the kit's manifests copy them.
     */
    private function installRecipes(): string
    {
        $root = $this->root().'/templates';
        $kit = self::kit();

        foreach (null === $kit ? [] : self::RECIPES as $recipe) {
            $target = $root.'/components/ui/'.substr($recipe, \strlen(explode('/components/', $recipe)[0]) + 12).'.html.twig';

            if (!is_dir(\dirname($target))) {
                mkdir(\dirname($target), recursive: true);
            }

            copy($kit.'/'.$recipe.'.html.twig', $target);
        }

        if (!is_dir($root)) {
            mkdir($root, recursive: true);
        }

        return $root;
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->parameters()->set('.ux_toolkit.components.subdirectory', 'ui');
        $container->extension('framework', [
            'secret' => 'test', 'test' => true, 'http_method_override' => false, 'php_errors' => ['log' => true],
            'default_locale' => 'en', 'translator' => ['fallbacks' => ['en']], 'router' => ['utf8' => true],
            'session' => ['storage_factory_id' => 'session.storage.factory.mock_file'],
            'csrf_protection' => true, 'form' => ['csrf_protection' => true],
        ]);
        $container->extension('doctrine', ['dbal' => ['url' => 'sqlite:///:memory:'], 'orm' => []]);
        $container->extension('twig', ['default_path' => '%kernel.project_dir%/templates', 'paths' => [$this->installRecipes()], 'strict_variables' => true]);
        $container->extension('twig_component', ['anonymous_template_directory' => 'components/', 'defaults' => []]);
        $container->extension('tilttek_table_safety', ['table_access' => Tables::class, 'mercure' => ['hub' => HubInterface::class]]);

        $services = $container->services();
        $services->set(Tables::class)->public();
        $services->set(HubInterface::class, RecordingHub::class)->public();
        $services->set('clock', MockClock::class)->args(['2026-11-12 20:00:00 UTC'])->public();
    }

    private function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import('@TilttekTableSafetyBundle/config/routes.php');
    }
}
