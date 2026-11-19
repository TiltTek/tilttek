<?php

declare(strict_types=1);

namespace Tilttek\LuckTokensBundle\Tests\Functional\App;

use Override;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Tilttek\LuckTokens\Event\HolderErased;
use Tilttek\LuckTokens\Event\SessionStarted;
use Tilttek\LuckTokens\Event\TokensGranted;
use Tilttek\LuckTokens\Event\TokensSpent;
use Tilttek\LuckTokens\Event\TokensTransferred;

/**
 * An application as small as the bundle needs: FrameworkBundle with its locks in memory, DoctrineBundle on SQLite, the
 * privacy bundle, the bundle with one table in its configuration and the locks enabled, and a listener of its events.
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
        return sys_get_temp_dir().'/tilttek-luck-tokens-bundle-test/'.hash('xxh128', __DIR__).'/cache/'.$this->environment;
    }

    #[Override]
    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/tilttek-luck-tokens-bundle-test/'.hash('xxh128', __DIR__).'/log';
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', ['secret' => 'test', 'test' => true, 'http_method_override' => false, 'php_errors' => ['log' => true], 'lock' => 'in-memory']);
        $container->extension('doctrine', ['dbal' => ['url' => 'sqlite:///:memory:'], 'orm' => []]);
        $container->extension('tilttek_luck_tokens', [
            'tables' => [
                'table:12' => [
                    'game_masters' => ['member:7'],
                    'kinds' => [
                        ['key' => 'inspiration', 'name' => 'Inspiration', 'cap' => 1, 'transferable' => true],
                        ['key' => 'fate', 'name' => 'Fate points', 'cap' => 10, 'refresh' => 'each-session', 'refresh_to' => 3],
                    ],
                ],
            ],
            'lock' => ['enabled' => true],
        ]);

        $container->services()->set('clock', MockClock::class)->args(['2026-11-13 19:30:00.250000', 'America/Toronto'])->public();
        $heard = $container->services()->set('app.heard', HeardEvents::class)->public();

        foreach ([TokensGranted::class, TokensSpent::class, TokensTransferred::class, SessionStarted::class, HolderErased::class] as $event) {
            $heard->tag('kernel.event_listener', ['event' => $event, 'method' => 'heard']);
        }
    }
}
