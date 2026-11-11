<?php

declare(strict_types=1);

namespace Tilttek\PointsBundle\Tests\Functional\App;

use Override;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;

/**
 * An application as small as the bundle needs: FrameworkBundle, DoctrineBundle on SQLite, the privacy bundle, the
 * journal of activity, and the bundle with two currencies and two rules.
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
        return sys_get_temp_dir().'/tilttek-points-bundle-test/'.hash('xxh128', __DIR__).'/cache/'.$this->environment;
    }

    #[Override]
    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/tilttek-points-bundle-test/'.hash('xxh128', __DIR__).'/log';
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', ['secret' => 'test', 'test' => true, 'http_method_override' => false, 'php_errors' => ['log' => true]]);
        $container->extension('doctrine', ['dbal' => ['url' => 'sqlite:///:memory:'], 'orm' => []]);
        $container->extension('tilttek_activity', ['verbs' => ['game.*', 'news.commented']]);
        $container->extension('tilttek_points', [
            'currencies' => ['points' => null, 'stars' => 'P1M'],
            'rules' => [
                'games' => ['verbs' => ['game.played'], 'points' => 10, 'cap' => ['amount' => 15, 'period' => 'week']],
                'comments' => ['verbs' => ['news.commented'], 'points' => 2, 'currency' => 'stars'],
            ],
        ]);

        $container->services()->set('clock', MockClock::class)->args(['2026-11-07 03:00:00 UTC'])->public();
    }
}
