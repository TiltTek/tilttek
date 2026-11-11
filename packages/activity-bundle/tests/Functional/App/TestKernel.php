<?php

declare(strict_types=1);

namespace Tilttek\ActivityBundle\Tests\Functional\App;

use Override;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Tilttek\ActivityBundle\Infrastructure\Messenger\CatchUpProjection;

/**
 * An application as small as the bundle needs: FrameworkBundle, DoctrineBundle on SQLite, the privacy bundle, the
 * bundle with declared verbs, and one projection found by its interface. In the `async` environment, the projections
 * follow the journal through Messenger, whose messages go to a transport in memory.
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
        return sys_get_temp_dir().'/tilttek-activity-bundle-test/'.hash('xxh128', __DIR__).'/cache/'.$this->environment;
    }

    #[Override]
    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/tilttek-activity-bundle-test/'.hash('xxh128', __DIR__).'/log';
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', ['secret' => 'test', 'test' => true, 'http_method_override' => false, 'php_errors' => ['log' => true]]);
        $container->extension('doctrine', ['dbal' => ['url' => 'sqlite:///:memory:'], 'orm' => []]);
        if ('async' === $this->environment) {
            $container->extension('framework', ['messenger' => ['transports' => ['async' => 'in-memory://'], 'routing' => [CatchUpProjection::class => 'async']]]);
            $container->extension('tilttek_activity', ['verbs' => ['game.*', 'news.commented'], 'projections' => 'messenger']);
        } else {
            $container->extension('tilttek_activity', ['verbs' => ['game.*', 'news.commented']]);
        }

        $services = $container->services()->defaults()->autowire()->autoconfigure();
        $services->set('clock', MockClock::class)->args(['2026-11-07 03:00:00 UTC'])->public();
        $services->set(GamesPlayed::class)->public();
    }
}
