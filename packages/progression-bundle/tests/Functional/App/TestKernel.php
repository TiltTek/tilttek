<?php

declare(strict_types=1);

namespace Tilttek\ProgressionBundle\Tests\Functional\App;

use Override;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Tilttek\Progression\ReachedMilestone;
use Tilttek\Progression\Streak\StreakBroken;
use Tilttek\ProgressionBundle\Infrastructure\Event\LevelDown;
use Tilttek\ProgressionBundle\Infrastructure\Event\LevelUp;

/**
 * An application as small as the bundle needs: FrameworkBundle with its translator, DoctrineBundle on SQLite,
 * TwigBundle, the privacy bundle, the journal of activity, and the bundle with a track, a seasonal track and a streak.
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
        return sys_get_temp_dir().'/tilttek-progression-bundle-test/'.hash('xxh128', __DIR__).'/cache/'.$this->environment;
    }

    #[Override]
    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/tilttek-progression-bundle-test/'.hash('xxh128', __DIR__).'/log';
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', ['secret' => 'test', 'test' => true, 'http_method_override' => false, 'php_errors' => ['log' => true], 'translator' => ['default_path' => '%kernel.project_dir%/translations', 'fallbacks' => ['en']], 'default_locale' => 'en']);
        $container->extension('doctrine', ['dbal' => ['url' => 'sqlite:///:memory:'], 'orm' => []]);
        $container->extension('twig', ['strict_variables' => true]);
        $container->extension('tilttek_activity', ['verbs' => ['game.*']]);
        $container->extension('tilttek_progression', [
            'timezone' => 'America/Toronto',
            'tracks' => [
                'player' => [
                    'curve' => ['type' => 'linear', 'step' => 100, 'levels' => 5],
                    'rules' => ['games' => ['verbs' => ['game.played'], 'experience' => 40]],
                    'milestones' => ['veteran' => ['level' => 2]],
                ],
                'monthly' => [
                    'curve' => ['type' => 'linear', 'step' => 50],
                    'seasons' => ['length' => 'month'],
                    'rules' => ['wins' => ['verbs' => ['game.won'], 'experience' => 10]],
                ],
            ],
            'streaks' => ['daily-play' => ['verbs' => ['game.played']]],
            'timezone_context' => 'timezone',
        ]);

        $container->services()->set('clock', MockClock::class)->args(['2026-11-07 03:00:00 UTC'])->public();
        $container->services()->set(Recorder::class)->public()
            ->tag('kernel.event_listener', ['event' => LevelUp::class, 'method' => 'up'])
            ->tag('kernel.event_listener', ['event' => LevelDown::class, 'method' => 'down'])
            ->tag('kernel.event_listener', ['event' => ReachedMilestone::class, 'method' => 'milestone'])
            ->tag('kernel.event_listener', ['event' => StreakBroken::class, 'method' => 'broken']);
    }
}
