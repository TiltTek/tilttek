<?php

declare(strict_types=1);

namespace Tilttek\DiceBundle\Tests\Functional\App;

use Override;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;

/**
 * An application as small as the bundle needs: FrameworkBundle with forms, validation and the translator, TwigBundle,
 * DoctrineBundle on SQLite, and the bundle with a coin, Finnish letters and the members' dice sets.
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
        return sys_get_temp_dir().'/tilttek-dice-bundle-test/'.hash('xxh128', __DIR__).'/cache/'.$this->environment;
    }

    #[Override]
    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/tilttek-dice-bundle-test/'.hash('xxh128', __DIR__).'/log';
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'test', 'test' => true, 'http_method_override' => false, 'php_errors' => ['log' => true],
            'default_locale' => 'en', 'translator' => ['fallbacks' => ['en']], 'form' => ['csrf_protection' => false], 'validation' => true,
        ]);
        $container->extension('twig', ['default_path' => '%kernel.project_dir%/templates', 'strict_variables' => true]);
        $container->extension('doctrine', ['dbal' => ['url' => 'sqlite:///:memory:']]);
        $container->extension('tilttek_dice', [
            'custom_dice' => ['coin' => ['faces' => [['id' => 'heads', 'label' => 'Heads', 'value' => 1, 'shape' => 'circle'], ['id' => 'tails', 'label' => 'Tails', 'value' => 0, 'shape' => 'square']]]],
            'notations' => ['fi' => ['die' => 'n']],
            'appearance' => MemberDice::class,
        ]);

        $services = $container->services()->defaults()->autowire()->autoconfigure();
        $services->set('clock', MockClock::class)->args(['2026-11-07 03:00:00 UTC'])->public();
        $services->set(MemberDice::class);
        $services->set(HeardRolls::class)->public();
        $services->alias('test.form.factory', 'form.factory')->public();
    }
}
