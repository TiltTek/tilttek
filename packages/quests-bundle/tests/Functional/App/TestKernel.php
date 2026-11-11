<?php

declare(strict_types=1);

namespace Tilttek\QuestsBundle\Tests\Functional\App;

use Override;
use Phil\Almanac\Almanac;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Tilttek\Quests\QuestCompleted;
use Tilttek\QuestsBundle\Infrastructure\Event\DuelExpired;
use Tilttek\QuestsBundle\Infrastructure\Event\DuelWon;

/**
 * An application as small as the bundle needs: FrameworkBundle with its translator, DoctrineBundle on SQLite,
 * TwigBundle, the privacy bundle when it is installed, the journal of activity, and the bundle with a personal quest,
 * a quest that comes back each year — when phil/almanac is installed, else a quest that opens on Good Friday 2027 —
 * and a duel.
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
        return sys_get_temp_dir().'/tilttek-quests-bundle-test/'.hash('xxh128', __DIR__).'/cache/'.$this->environment;
    }

    #[Override]
    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/tilttek-quests-bundle-test/'.hash('xxh128', __DIR__).'/log';
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', ['secret' => 'test', 'test' => true, 'http_method_override' => false, 'php_errors' => ['log' => true], 'translator' => ['default_path' => '%kernel.project_dir%/translations', 'fallbacks' => ['en']], 'default_locale' => 'en']);
        $container->extension('doctrine', ['dbal' => ['url' => 'sqlite:///:memory:'], 'orm' => []]);
        $container->extension('twig', ['strict_variables' => true]);
        $container->extension('tilttek_activity', ['verbs' => ['game.*', 'egg.*'], 'dates' => ['past' => null, 'ahead' => 'P1Y']]);
        $container->extension('tilttek_quests', [
            'timezone' => 'America/Toronto',
            'quests' => [
                'first-steps' => [
                    'ordered' => true,
                    'steps' => [['code' => 'play', 'verbs' => ['game.played'], 'count' => 2], ['code' => 'win', 'verbs' => ['game.won']]],
                    'rewards' => [['kind' => 'points', 'code' => 'xp', 'amount' => 50]],
                ],
                'easter-hunt' => [
                    ...(class_exists(Almanac::class) ? ['recurs' => ['from' => 'easter - 2 days', 'until' => 'easter + 1 day']] : ['opens' => '2027-03-26']),
                    'steps' => [['code' => 'find', 'verbs' => ['egg.found'], 'count' => 3]],
                ],
                'race' => ['kind' => 'duel', 'duel' => ['length' => 'P1D'], 'steps' => [['code' => 'play', 'verbs' => ['game.played'], 'count' => 2]]],
            ],
        ]);

        $container->services()->set('clock', MockClock::class)->args(['2026-11-07 03:00:00 UTC'])->public();
        $container->services()->set(Recorder::class)->public()
            ->tag('kernel.event_listener', ['event' => QuestCompleted::class, 'method' => 'completed'])
            ->tag('kernel.event_listener', ['event' => DuelWon::class, 'method' => 'won'])
            ->tag('kernel.event_listener', ['event' => DuelExpired::class, 'method' => 'expired']);
    }
}
