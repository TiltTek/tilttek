<?php

declare(strict_types=1);

namespace Tilttek\LootTablesBundle\Tests\Functional\App;

use Override;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Tilttek\LootTablesBundle\Tests\Unit\Fixture\Shop;

/**
 * An application as small as the bundle needs: FrameworkBundle with forms and the translator in French, DoctrineBundle
 * on an in-memory SQLite database, Twig with TwigComponent, the commerce core, and the bundle with the catalogue of the
 * test shop, whose options autoconfiguration plugs into the core; the recipes of the PHIL UX kit are copied where
 * `ux:install` would put them, in the subdirectory `ui` of the Toolkit, which its parameter stands for.
 */
final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    /**
     * The recipes of the kit the buyer's draws are drawn with.
     */
    private const array RECIPES = [
        'badge/templates/components/Badge',
        'card/templates/components/Card',
        'card/templates/components/Card/Header',
        'card/templates/components/Card/Title',
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
        return $this->root().'/cache';
    }

    #[Override]
    public function getLogDir(): string
    {
        return $this->root().'/log';
    }

    private function root(): string
    {
        return sys_get_temp_dir().'/tilttek-loot-tables-bundle-test/'.hash('xxh128', __DIR__);
    }

    /**
     * The templates of the recipes, where the kit's manifests copy them.
     */
    private function installRecipes(): string
    {
        $root = $this->root().'/templates';
        $kit = self::kit();

        foreach (null === $kit ? [] : self::RECIPES as $recipe) {
            $target = $root.'/components/ui/'.explode('/components/', $recipe)[1].'.html.twig';

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
            'secret' => 'test',
            'test' => true,
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
            'default_locale' => 'fr',
            'translator' => ['fallbacks' => ['en']],
            'form' => ['csrf_protection' => false],
            'csrf_protection' => false,
            'router' => ['utf8' => true],
        ]);
        $container->extension('twig', ['paths' => [$this->installRecipes()], 'strict_variables' => true]);
        $container->extension('twig_component', ['anonymous_template_directory' => 'components/', 'defaults' => []]);
        $container->services()->set('logger', NullLogger::class);
        $container->extension('doctrine', ['dbal' => ['url' => 'sqlite:///:memory:'], 'orm' => []]);
        $container->extension('phil_commerce', []);
        $container->extension('tilttek_loot_tables', Shop::CATALOGUE);
        $container->services()->defaults()->autoconfigure()->set(Shop::class);
        $container->services()->alias('test.form_factory', 'form.factory')->public();
    }
}
