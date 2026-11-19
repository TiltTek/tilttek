<?php

declare(strict_types=1);

namespace Tilttek\DiceProbabilityBundle\Tests\Functional\App;

use FilesystemIterator;
use Override;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Tilttek\DiceProbabilityBundle\Infrastructure\Twig\KitComponents;

/**
 * An application as small as the bundle needs: FrameworkBundle with the translator in French, TwigBundle with
 * TwigComponent and LiveComponent, the recipes of the PHIL UX kit copied where `ux:install` would put them, in the
 * subdirectory of the application's Toolkit configuration — the Toolkit itself stood for by its parameter —,
 * TilttekDiceBundle with a custom die and its journal in memory, and the bundle with a shallow explosion depth and few
 * samples.
 */
final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    public function __construct(
        string $environment,
        bool $debug,
        private readonly string $subdirectory = 'ui',
    ) {
        parent::__construct($environment, $debug);
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
        return sys_get_temp_dir().'/tilttek-dice-probability-bundle-test/'.hash('xxh128', __DIR__.$this->subdirectory);
    }

    /**
     * The templates of the recipes, where the kit's manifests copy them.
     */
    private function installRecipes(): string
    {
        $target = rtrim($this->root().'/templates/components/'.$this->subdirectory, '/');

        // The kit among the development requirements of the monorepo, or of the package alone.
        $kit = is_dir(__DIR__.'/../../../../../vendor/phil/ux-kit') ? __DIR__.'/../../../../../vendor/phil/ux-kit' : __DIR__.'/../../../vendor/phil/ux-kit';

        foreach (KitComponents::RECIPES as $recipe) {
            $source = $kit.'/'.$recipe.'/templates/components';
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS));

            foreach ($files as $file) {
                \assert($file instanceof SplFileInfo);
                $copy = $target.substr($file->getPathname(), \strlen($source));

                if (!is_dir(\dirname($copy))) {
                    mkdir(\dirname($copy), recursive: true);
                }

                copy($file->getPathname(), $copy);
            }
        }

        return $this->root().'/templates';
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'test', 'test' => true, 'http_method_override' => false, 'php_errors' => ['log' => true],
            'default_locale' => 'fr', 'translator' => ['fallbacks' => ['en']],
        ]);
        $container->parameters()->set('.ux_toolkit.components.subdirectory', '' === $this->subdirectory ? null : $this->subdirectory);
        $container->extension('twig', ['default_path' => '%kernel.project_dir%/templates', 'paths' => [$this->installRecipes()], 'strict_variables' => true]);
        $container->extension('twig_component', ['anonymous_template_directory' => 'components/', 'defaults' => []]);
        $container->extension('tilttek_dice', [
            'store' => 'memory',
            'custom_dice' => ['coin' => ['faces' => [['id' => 'heads', 'label' => 'Heads', 'value' => 1, 'shape' => 'circle'], ['id' => 'tails', 'label' => 'Tails', 'value' => 0, 'shape' => 'square']]]],
        ]);
        $container->extension('tilttek_dice_probability', ['explosion_depth' => 2, 'samples' => 200, 'rounding' => 'ceil']);
    }

    private function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import('@LiveComponentBundle/config/routes.php')->prefix('/_components');
    }
}
