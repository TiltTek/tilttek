<?php

declare(strict_types=1);

namespace Tilttek\LeaderboardsBundle\Tests\Functional\App;

use Override;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Tilttek\LeaderboardsBundle\Infrastructure\Event\LeagueSettled;
use Tilttek\LeaderboardsBundle\Infrastructure\Event\SnapshotTaken;
use Tilttek\LeaderboardsBundle\Infrastructure\Event\TopReached;

/**
 * An application as small as the bundle needs: FrameworkBundle with its translator, DoctrineBundle on SQLite,
 * TwigBundle and Twig components, the privacy bundle, the journal of activity, and the bundle with a monthly board, a
 * reported one, a weekly league and a cup of teams — the first places celebrated as scores change in the environment
 * `lead`, once the period closed otherwise —; the recipes of the PHIL UX kit are copied where `ux:install` would
 * put them, in the subdirectory `ui` of the Toolkit, which its parameter stands for.
 */
final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    /**
     * The recipes of the kit the screens are built of.
     */
    private const array RECIPES = [
        'badge/templates/components/Badge',
        'card/templates/components/Card',
        'card/templates/components/Card/Header',
        'card/templates/components/Card/Title',
        'card/templates/components/Card/Description',
        'card/templates/components/Card/Content',
        'card/templates/components/Card/Footer',
        'empty/templates/components/Empty',
        'empty/templates/components/Empty/Header',
        'empty/templates/components/Empty/Title',
        'empty/templates/components/Empty/Description',
        'celebration/templates/components/Celebration',
    ];

    /**
     * The PHIL UX kit in a PHIL monorepo beside the Tilttek one, or among the development requirements; null when none
     * has it.
     */
    public static function kit(): ?string
    {
        foreach (['phil', 'phil-prerelease'] as $monorepo) {
            if (is_file($kit = __DIR__.'/../../../../../../../Phil/'.$monorepo.'/packages/ux-kit/manifest.json')) {
                return \dirname($kit);
            }
        }

        foreach ([__DIR__.'/../../../../../vendor/phil/ux-kit', __DIR__.'/../../../vendor/phil/ux-kit'] as $kit) {
            if (is_file($kit.'/manifest.json')) {
                return $kit;
            }
        }

        return null;
    }

    #[Override]
    public function getProjectDir(): string
    {
        return __DIR__;
    }

    #[Override]
    public function getCacheDir(): string
    {
        return sys_get_temp_dir().'/tilttek-leaderboards-bundle-test/'.hash('xxh128', __DIR__).'/cache/'.$this->environment;
    }

    #[Override]
    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/tilttek-leaderboards-bundle-test/'.hash('xxh128', __DIR__).'/log';
    }

    /**
     * The templates of the recipes, where the kit's manifests copy them.
     */
    private function installRecipes(): string
    {
        $root = sys_get_temp_dir().'/tilttek-leaderboards-bundle-test/'.hash('xxh128', __DIR__).'/templates';
        $kit = self::kit();

        foreach (null === $kit ? [] : self::RECIPES as $recipe) {
            $target = $root.'/components/ui/'.substr($recipe, \strlen(explode('/components/', $recipe)[0]) + 12).'.html.twig';

            if (!is_dir(\dirname($target))) {
                mkdir(\dirname($target), recursive: true);
            }

            copy($kit.'/'.$recipe.'.html.twig', $target);
        }

        if (!is_dir($root)) {
            mkdir($root, recursive: true);
        }

        return $root;
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->parameters()->set('.ux_toolkit.components.subdirectory', 'ui');
        $container->extension('framework', ['secret' => 'test', 'test' => true, 'http_method_override' => false, 'php_errors' => ['log' => true], 'translator' => ['default_path' => '%kernel.project_dir%/translations', 'fallbacks' => ['en']], 'default_locale' => 'en']);
        $container->extension('doctrine', ['dbal' => ['url' => 'sqlite:///:memory:'], 'orm' => []]);
        $container->extension('twig', ['strict_variables' => true, 'paths' => [$this->installRecipes()]]);
        $container->extension('twig_component', ['anonymous_template_directory' => 'components/', 'defaults' => []]);
        $container->extension('tilttek_activity', ['verbs' => ['game.*']]);
        $container->extension('tilttek_leaderboards', [
            'timezone' => 'America/Toronto',
            'boards' => [
                'games-month' => ['measure' => 'total', 'verbs' => ['game.played'], 'period' => 'month', 'snapshot_size' => 2],
                'level' => ['measure' => 'latest'],
                'weekly' => ['measure' => 'total', 'verbs' => ['game.played'], 'period' => 'week', 'league' => ['tiers' => ['bronze', 'silver'], 'division_size' => 2, 'promoted' => 1, 'relegated' => 1]],
                'cup' => ['measure' => 'total', 'verbs' => ['game.played'], 'chance' => ['dice' => 1, 'faces' => 6]],
            ],
            'schedule' => true,
            // The environment `lead` celebrates the first places as scores change.
            'celebration' => ['celebrate_on' => 'lead' === $this->environment ? 'score_change' : 'period_close'],
        ]);

        $container->services()->set('clock', MockClock::class)->args(['2026-11-07 12:00:00 UTC'])->public();
        $container->services()->set(Recorder::class)->public()
            ->tag('kernel.event_listener', ['event' => SnapshotTaken::class, 'method' => 'snapshot'])
            ->tag('kernel.event_listener', ['event' => LeagueSettled::class, 'method' => 'settled'])
            ->tag('kernel.event_listener', ['event' => TopReached::class, 'method' => 'top']);
    }
}
