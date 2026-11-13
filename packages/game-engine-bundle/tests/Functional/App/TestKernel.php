<?php

declare(strict_types=1);

namespace Tilttek\GameEngineBundle\Tests\Functional\App;

use Override;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Tilttek\GameEngineBundle\Tests\Fixture\RollRace;

/**
 * An application as small as the bundle needs: FrameworkBundle with its translator and an in-memory lock,
 * DoctrineBundle on SQLite, TwigBundle and Twig components, a Mercure hub that records, a resolver of the seat, the
 * race of dice as a game, and the bundle with all of it; the recipes of the PHIL UX kit are copied where `ux:install`
 * would put them, in the subdirectory `ui` of the Toolkit, which its parameter stands for.
 */
final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    /**
     * The recipes of the kit the template of a table is built of.
     */
    private const array RECIPES = [
        'badge/templates/components/Badge',
        'card/templates/components/Card',
        'card/templates/components/Card/Header',
        'card/templates/components/Card/Title',
        'card/templates/components/Card/Description',
        'card/templates/components/Card/Content',
        'card/templates/components/Card/Footer',
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
        return sys_get_temp_dir().'/tilttek-game-engine-bundle-test/'.hash('xxh128', __DIR__).'/cache/'.$this->environment;
    }

    #[Override]
    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/tilttek-game-engine-bundle-test/'.hash('xxh128', __DIR__).'/log';
    }

    /**
     * The templates of the recipes, where the kit's manifests copy them.
     */
    private function installRecipes(): string
    {
        $root = sys_get_temp_dir().'/tilttek-game-engine-bundle-test/'.hash('xxh128', __DIR__).'/templates';
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
        $container->extension('framework', ['secret' => 'test', 'test' => true, 'http_method_override' => false, 'php_errors' => ['log' => true], 'lock' => 'in-memory', 'translator' => ['fallbacks' => ['en']], 'default_locale' => 'en', 'router' => ['utf8' => true]]);
        $container->extension('doctrine', ['dbal' => ['url' => 'sqlite:///:memory:']]);
        $container->extension('twig', ['strict_variables' => true, 'paths' => [$this->installRecipes(), __DIR__.'/templates']]);
        $container->extension('twig_component', ['anonymous_template_directory' => 'components/', 'defaults' => []]);
        $container->extension('tilttek_game_engine', [
            'lock' => ['factory' => 'lock.factory'],
            'mercure' => ['enabled' => true],
            'view' => ['resolver' => SeatHeader::class],
            'roleplaying_scene' => true,
        ]);

        $services = $container->services()->defaults()->autowire()->autoconfigure();
        $services->set(RollRace::class);
        $services->set(SeatHeader::class);
        $services->set(HubRecorder::class)->public();
        $services->set(HubInterface::class)->factory([new Reference(HubRecorder::class), 'hub']);
    }

    private function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import('@TilttekGameEngineBundle/config/routes.php')->prefix('/game-tables');
    }
}
