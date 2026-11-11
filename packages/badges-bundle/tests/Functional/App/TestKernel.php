<?php

declare(strict_types=1);

namespace Tilttek\BadgesBundle\Tests\Functional\App;

use Override;
use Phil\OpenBadgesBundle\PhilOpenBadgesBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Tilttek\BadgesBundle\Infrastructure\Event\BadgeAwarded;
use Tilttek\BadgesBundle\Infrastructure\Event\BadgeRevoked;

/**
 * An application as small as the bundle needs: FrameworkBundle, DoctrineBundle on SQLite, TwigBundle, the privacy
 * bundle, the journal of activity, phil/open-badges-bundle with an issuer whose key is an Ed25519 seed, and the bundle
 * with three badges and their Open Badges credentials.
 */
final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    /**
     * Thirty-two bytes of 1, in base 64.
     */
    public const string SEED = 'AQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQE=';

    #[Override]
    public function getProjectDir(): string
    {
        return __DIR__;
    }

    #[Override]
    public function getCacheDir(): string
    {
        return sys_get_temp_dir().'/tilttek-badges-bundle-test/'.hash('xxh128', __DIR__).'/cache/'.$this->environment;
    }

    #[Override]
    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/tilttek-badges-bundle-test/'.hash('xxh128', __DIR__).'/log';
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', ['secret' => 'test', 'test' => true, 'http_method_override' => false, 'php_errors' => ['log' => true]]);
        $container->extension('doctrine', ['dbal' => ['url' => 'sqlite:///:memory:'], 'orm' => []]);
        $container->extension('twig', ['strict_variables' => true]);
        $container->extension('tilttek_activity', ['verbs' => ['news.*', 'site.visited']]);
        $container->extension('tilttek_badges', [
            'badges' => [
                'commenter' => ['name' => 'Commenter', 'revocable' => true, 'criterion' => ['count' => ['verbs' => ['news.commented'], 'times' => 2]]],
                'regular' => ['name' => 'Regular', 'criterion' => ['streak' => ['verbs' => ['site.visited'], 'days' => 2]]],
                'organiser' => ['name' => 'Organiser', 'image' => 'https://example.org/organiser.png', 'criterion' => ['manual' => true]],
            ],
            ...(class_exists(PhilOpenBadgesBundle::class) ? ['open_badges' => ['achievement_url' => 'https://example.org/badges/{code}']] : []),
        ]);

        if (class_exists(PhilOpenBadgesBundle::class)) {
            $container->extension('phil_open_badges', [
                'issuer' => ['id' => 'https://example.org/issuer', 'name' => 'Example'],
                'key' => ['secret' => '%env(base64:OPEN_BADGES_SEED)%'],
                'verification' => false,
            ]);
        }

        $container->parameters()->set('env(OPEN_BADGES_SEED)', self::SEED);
        $container->services()->set('clock', MockClock::class)->args(['2026-11-07 03:00:00 UTC'])->public();
        $container->services()->set(Recorder::class)->public()->tag('kernel.event_listener', ['event' => BadgeAwarded::class, 'method' => 'awarded'])->tag('kernel.event_listener', ['event' => BadgeRevoked::class, 'method' => 'revoked']);
    }
}
