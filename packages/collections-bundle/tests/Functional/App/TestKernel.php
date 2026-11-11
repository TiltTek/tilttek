<?php

declare(strict_types=1);

namespace Tilttek\CollectionsBundle\Tests\Functional\App;

use Override;
use Phil\Almanac\Almanac;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Tilttek\Collections\SetCompleted;
use Tilttek\CollectionsBundle\Infrastructure\Event\CopyGranted;

/**
 * An application as small as the bundle needs: FrameworkBundle with its translator, DoctrineBundle on SQLite,
 * TwigBundle, the privacy bundle when it is installed, the journal of activity, and the bundle with a crew to collect,
 * a base set of dice, a pack, and the item of the day claimed on a page viewed — Easter 2027 computed by phil/almanac
 * when it is installed, else the same day as a fixed date.
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
        return sys_get_temp_dir().'/tilttek-collections-bundle-test/'.hash('xxh128', __DIR__).'/cache/'.$this->environment;
    }

    #[Override]
    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/tilttek-collections-bundle-test/'.hash('xxh128', __DIR__).'/log';
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', ['secret' => 'test', 'test' => true, 'http_method_override' => false, 'php_errors' => ['log' => true], 'translator' => ['default_path' => '%kernel.project_dir%/translations', 'fallbacks' => ['en']], 'default_locale' => 'en']);
        $container->extension('doctrine', ['dbal' => ['url' => 'sqlite:///:memory:'], 'orm' => []]);
        $container->extension('twig', ['strict_variables' => true]);
        $container->extension('tilttek_activity', ['verbs' => ['page.*'], 'dates' => ['past' => null, 'ahead' => 'P1Y']]);
        $container->extension('tilttek_collections', [
            'timezone' => 'America/Toronto',
            'rarities' => ['common' => 3, 'rare' => 1],
            'items' => [
                'deckhand' => ['name' => ['en' => 'Deckhand', 'fr' => 'Matelot'], 'rarity' => 'common', 'sets' => ['crew'], 'image' => '/img/deckhand.webp'],
                'pirate' => ['name' => 'Pirate', 'rarity' => 'rare', 'sets' => ['crew'], 'description' => 'Sails the seas.'],
                'ember-d6' => ['name' => 'Ember d6', 'rarity' => 'common', 'sets' => ['ember'], 'die' => ['sides' => 6, 'color' => '#c0392b']],
            ],
            'sets' => [
                'crew' => ['name' => ['en' => 'The crew', 'fr' => "L'équipage"], 'rewards' => [['kind' => 'points', 'code' => 'xp', 'amount' => 50]]],
                'ember' => ['name' => 'Ember dice', 'kind' => 'base'],
            ],
            'packs' => ['booster' => ['name' => 'Booster', 'size' => 2, 'sets' => ['crew']]],
            'daily' => ['enabled' => true, 'verbs' => ['page.viewed'], 'sets' => ['crew'], ...(class_exists(Almanac::class)
                ? ['dates' => ['11-07' => 'pirate'], 'computed' => [['rule' => 'easter', 'item' => 'deckhand']]]
                : ['dates' => ['11-07' => 'pirate', '2027-03-28' => 'deckhand']])],
        ]);

        $container->services()->set('clock', MockClock::class)->args(['2026-11-07 15:00:00 UTC'])->public();
        $container->services()->set(Recorder::class)->public()
            ->tag('kernel.event_listener', ['event' => CopyGranted::class, 'method' => 'granted'])
            ->tag('kernel.event_listener', ['event' => SetCompleted::class, 'method' => 'completed']);
    }
}
