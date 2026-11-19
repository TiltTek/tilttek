<?php

declare(strict_types=1);

namespace Tilttek\ExchangeBundle\Tests\Functional\App;

use Override;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Tilttek\Exchange\Event\OfferAccepted;
use Tilttek\Exchange\Event\OfferMade;
use Tilttek\Exchange\Event\PointsTransferred;
use Tilttek\Exchange\Event\ReservationConfirmed;
use Tilttek\Exchange\Event\ReservationExpired;
use Tilttek\Exchange\Event\RewardReserved;
use Tilttek\Exchange\Trade\InMemoryInventory;

/**
 * An application as small as the bundle needs: FrameworkBundle, DoctrineBundle on SQLite, the privacy bundle, the
 * points, the bundle with two rewards, transfers and trades over an inventory in memory, and a listener of its events.
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
        return sys_get_temp_dir().'/tilttek-exchange-bundle-test/'.hash('xxh128', __DIR__).'/cache/'.$this->environment;
    }

    #[Override]
    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/tilttek-exchange-bundle-test/'.hash('xxh128', __DIR__).'/log';
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', ['secret' => 'test', 'test' => true, 'http_method_override' => false, 'php_errors' => ['log' => true], 'form' => ['enabled' => true, 'csrf_protection' => false]]);
        $container->extension('doctrine', ['dbal' => ['url' => 'sqlite:///:memory:'], 'orm' => []]);
        $container->extension('tilttek_points', ['currencies' => ['points' => null]]);
        $container->extension('tilttek_exchange', [
            'rewards' => [
                'discount-10' => ['price' => 100],
                'seat' => ['price' => 250, 'stock' => 1],
            ],
            'transfers' => ['enabled' => true, 'allowance' => ['limit' => 50, 'period' => 'week']],
            'trades' => ['enabled' => true, 'inventory' => 'app.inventory'],
        ]);

        $container->services()->set('clock', MockClock::class)->args(['2026-11-07 12:00:00 UTC'])->public();
        $container->services()->set('app.inventory', InMemoryInventory::class)->public();
        $container->services()->alias('app.form_factory', 'form.factory')->public();
        $heard = $container->services()->set('app.heard', HeardEvents::class)->public();

        foreach ([RewardReserved::class, ReservationConfirmed::class, ReservationExpired::class, PointsTransferred::class, OfferMade::class, OfferAccepted::class] as $event) {
            $heard->tag('kernel.event_listener', ['event' => $event, 'method' => 'heard']);
        }
    }
}
