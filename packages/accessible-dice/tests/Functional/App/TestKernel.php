<?php

declare(strict_types=1);

namespace Tilttek\AccessibleDice\Tests\Functional\App;

use Override;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;

/**
 * An application as small as the bundle needs: FrameworkBundle with the translator in French, TwigBundle,
 * TilttekDiceBundle with a coin, its labels in French and a limit of 30 dice, and the bundle with three dice.
 */
final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    #[Override]
    public function getProjectDir(): string
    {
        return __DIR__;
    }

    #[Override]
    public function getCacheDir(): string
    {
        return sys_get_temp_dir().'/tilttek-accessible-dice-test/'.hash('xxh128', __DIR__).'/cache/'.$this->environment;
    }

    #[Override]
    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/tilttek-accessible-dice-test/'.hash('xxh128', __DIR__).'/log';
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'test', 'test' => true, 'http_method_override' => false, 'php_errors' => ['log' => true],
            'default_locale' => 'fr', 'translator' => ['fallbacks' => ['en'], 'default_path' => '%kernel.project_dir%/translations'],
        ]);
        $container->extension('twig', ['default_path' => '%kernel.project_dir%/templates', 'strict_variables' => true]);
        $container->extension('tilttek_dice', [
            'store' => 'memory',
            'limits' => ['dice' => 30],
            'custom_dice' => ['coin' => ['faces' => [['id' => 'heads', 'label' => 'coin.heads', 'value' => 1, 'shape' => 'circle'], ['id' => 'tails', 'label' => 'coin.tails', 'value' => 0, 'shape' => 'square', 'color' => '#336699']]]],
        ]);
        $container->extension('tilttek_accessible_dice', ['sides' => [6, 20]]);
    }
}
