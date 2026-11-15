<?php

declare(strict_types=1);

namespace Tilttek\LeaderboardsBundle;

use Override;
use Phil\PrivacyBundle\Application\Contract\PersonalDataEraser;
use Phil\PrivacyBundle\PhilPrivacyBundle;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Tilttek\LeaderboardsBundle\Infrastructure\DependencyInjection\KitSubdirectoryPass;
use Tilttek\LeaderboardsBundle\Infrastructure\DependencyInjection\LeaderboardsServices;
use Tilttek\LeaderboardsBundle\Infrastructure\Event\CelebrateOn;
use Tilttek\LeaderboardsBundle\Infrastructure\Lead\Contract\LeadStore;
use Tilttek\LeaderboardsBundle\Infrastructure\Privacy\LeaderboardsPrivacy;

/**
 * Leaderboards for a Symfony application, on `tilttek/leaderboards`:
 *
 * ```yaml
 * tilttek_leaderboards:
 *     timezone: America/Toronto
 *     boards:
 *         games-month: { measure: count, verbs: ['game.played'], period: month }
 *         high-score: { measure: best, verbs: ['arcade.scored'], period: week, ties: shared }
 *         level: { measure: latest }
 *         weekly-xp: { measure: total, verbs: ['lesson.done'], period: week, league: { tiers: [bronze, silver, gold], formed_by: volume } }
 *         regular: { measure: days, verbs: ['lesson.done'], period: window, window: 7, title: { name: regular, threshold: 5 } }
 * ```
 *
 * The configuration says where the leaderboards are kept — a Doctrine DBAL connection, or memory for tests, and the
 * scores in Valkey when it is enabled —, the boards with their measures, periods, timezones, seasons, windows, ties,
 * tie-breaks, snapshot sizes, leagues, objects, titles and luck, the secret the luck is drawn from, the timezone of the
 * boards that name none, the name of the projection that `tilttek/activity-bundle` catches up, whether the snapshots
 * and settlements due run from Symfony Scheduler, the translation domain of the templates, and under which kind a data
 * subject is a member.
 */
/**
 * @phpstan-import-type LeaderboardsConfig from LeaderboardsServices
 */
final class TilttekLeaderboardsBundle extends AbstractBundle
{
    #[Override]
    protected string $extensionAlias = 'tilttek_leaderboards';

    #[Override]
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new KitSubdirectoryPass());
    }

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->enumNode('store')
                    ->info('Where the leaderboards are kept: "doctrine", or "memory" for tests.')
                    ->values(['doctrine', 'memory'])
                    ->defaultValue('doctrine')
                ->end()
                ->arrayNode('valkey')
                    ->info('Whether the contributions and the scores are kept in the sorted sets of Valkey rather than in the store above; the snapshots, the choices and the leagues stay there.')
                    ->canBeEnabled()
                    ->children()
                        ->scalarNode('dsn')
                            ->info('The Valkey server, as Symfony Cache reads a Redis DSN: "redis://localhost:6379", "rediss://…", a socket, or a cluster.')
                            ->cannotBeEmpty()
                            ->defaultValue('redis://localhost:6379')
                        ->end()
                        ->scalarNode('client')
                            ->info('The service of a Valkey client the application already has, used instead of the DSN: a Redis or RedisCluster of the redis extension, a Relay client, a Predis client, or a ValkeyGlide client of valkey-glide-php.')
                            ->cannotBeEmpty()
                            ->defaultNull()
                        ->end()
                        ->scalarNode('prefix')
                            ->info('The prefix of every key, between braces so that a cluster keeps them on one node.')
                            ->cannotBeEmpty()
                            ->defaultValue('tilttek_leaderboards')
                        ->end()
                    ->end()
                ->end()
                ->scalarNode('connection')
                    ->info('The Doctrine DBAL connection of the leaderboards.')
                    ->cannotBeEmpty()
                    ->defaultValue('default')
                ->end()
                ->scalarNode('timezone')
                    ->info('The timezone of the periods of the boards that name none.')
                    ->cannotBeEmpty()
                    ->defaultValue('UTC')
                ->end()
                ->arrayNode('boards')
                    ->info('The boards by name: what they measure, the activities they read, their period and its timezone, their seasons, how they order a tie, how many members a snapshot keeps.')
                    ->useAttributeAsKey('name')
                    ->normalizeKeys(false)
                    ->arrayPrototype()
                        ->children()
                            ->enumNode('measure')
                                ->info('"count" the gestures, "total" their quantities, keep the "best" quantity of one gesture, count the "days" with a gesture — from the journal —; or keep the "latest" or the "highest" value the application reports.')
                                ->isRequired()
                                ->values(['count', 'total', 'best', 'days', 'latest', 'highest'])
                            ->end()
                            ->arrayNode('verbs')
                                ->info('The verbs the board reads from the journal, as patterns such as "game.played" or "game.*"; none for a reported measure.')
                                ->scalarPrototype()->end()
                            ->end()
                            ->arrayNode('context')
                                ->info('The facts the context of an activity must hold to count: integers, booleans or strings, by name.')
                                ->useAttributeAsKey('name')
                                ->scalarPrototype()->end()
                                ->validate()
                                    ->ifTrue(static fn (array $context): bool => array_any($context, static fn (mixed $value): bool => !\is_bool($value) && !\is_int($value) && !\is_string($value)))
                                    ->thenInvalid('A context condition is an integer, a boolean or a string.')
                                ->end()
                            ->end()
                            ->enumNode('period')
                                ->info('The period the board ranks over: day, week, month, quarter, year, season — with its "seasons" —, ever, or a sliding window — with its "window".')
                                ->values(['day', 'week', 'month', 'quarter', 'year', 'season', 'ever', 'window'])
                                ->defaultValue('ever')
                            ->end()
                            ->integerNode('window')
                                ->info('The days of a sliding window, today included, for a board ranked over one.')
                                ->min(0)
                                ->max(366)
                                ->defaultValue(0)
                            ->end()
                            ->scalarNode('timezone')
                                ->info("The timezone of the board's periods and seasons; null for the one above.")
                                ->defaultNull()
                            ->end()
                            ->arrayNode('seasons')
                                ->info('The seasons of a board ranked by season, each with its code, start and end.')
                                ->arrayPrototype()
                                    ->children()
                                        ->scalarNode('code')->isRequired()->end()
                                        ->scalarNode('start')->isRequired()->end()
                                        ->scalarNode('end')->isRequired()->end()
                                    ->end()
                                ->end()
                            ->end()
                            ->enumNode('ties')
                                ->info('Who ranks first on an equal score: the "first" to reach it, or nobody — the rank is "shared".')
                                ->values(['first', 'shared'])
                                ->defaultValue('first')
                            ->end()
                            ->arrayNode('tie_breaks')
                                ->info('Up to two measures that order equal scores, in turn, before the first to reach the score or a shared rank.')
                                ->arrayPrototype()
                                    ->children()
                                        ->enumNode('measure')
                                            ->info('What the tie-break gathers of the gestures: their "count", the "total", the "highest" or the "lowest" of their quantities or of a context integer; or the value "reported" with the score.')
                                            ->isRequired()
                                            ->values(['count', 'total', 'highest', 'lowest', 'reported'])
                                        ->end()
                                        ->enumNode('order')
                                            ->info('The highest value first ("desc"), or the lowest ("asc").')
                                            ->values(['desc', 'asc'])
                                            ->defaultValue('desc')
                                        ->end()
                                        ->scalarNode('context')
                                            ->info('The name of the context integer the tie-break reads instead of the quantity.')
                                            ->defaultNull()
                                        ->end()
                                    ->end()
                                ->end()
                            ->end()
                            ->booleanNode('per_object')
                                ->info('Whether the board ranks members for each object of the activities: a game, a book, a level.')
                                ->defaultFalse()
                            ->end()
                            ->arrayNode('title')
                                ->info('A title a member holds while their score in the period under way reaches the threshold — and nobody ranks before them, for a title of the leader.')
                                ->children()
                                    ->scalarNode('name')->isRequired()->end()
                                    ->integerNode('threshold')->isRequired()->min(1)->end()
                                    ->booleanNode('leader')->info('Whether the title goes to the leader alone, among those who reach the threshold.')->defaultFalse()->end()
                                ->end()
                            ->end()
                            ->arrayNode('chance')
                                ->info('The luck added to the score of a team: dice rolled once per team and period, from the secret.')
                                ->children()
                                    ->integerNode('dice')->min(1)->max(10)->defaultValue(1)->end()
                                    ->integerNode('faces')->min(2)->max(100)->defaultValue(6)->end()
                                ->end()
                            ->end()
                            ->integerNode('snapshot_size')
                                ->info('How many members a snapshot of the board keeps.')
                                ->min(1)
                                ->max(1000)
                                ->defaultValue(100)
                            ->end()
                            ->arrayNode('league')
                                ->info('The league of a weekly board: its tiers from the lowest, the size of a division, how many of a division go up and down at the end of the week.')
                                ->children()
                                    ->arrayNode('tiers')
                                        ->info('The tiers, from the lowest to the highest.')
                                        ->isRequired()
                                        ->requiresAtLeastOneElement()
                                        ->scalarPrototype()->end()
                                    ->end()
                                    ->integerNode('division_size')
                                        ->info('How many members a division holds.')
                                        ->min(2)
                                        ->max(100)
                                        ->defaultValue(30)
                                    ->end()
                                    ->integerNode('promoted')
                                        ->info('How many of the first of a division go up a tier.')
                                        ->min(0)
                                        ->defaultValue(7)
                                    ->end()
                                    ->integerNode('relegated')
                                        ->info('How many of the last of a full division go down a tier.')
                                        ->min(0)
                                        ->defaultValue(5)
                                    ->end()
                                    ->enumNode('formed_by')
                                        ->info('How the divisions of a tier are filled: in the order of "arrival", or by the "volume" of the week before.')
                                        ->values(['arrival', 'volume'])
                                        ->defaultValue('arrival')
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->scalarNode('projection')
                    ->info('The name of the projection that keeps the scores up to date from the activity.')
                    ->cannotBeEmpty()
                    ->defaultValue(LeaderboardsServices::DEFAULT_PROJECTION)
                ->end()
                ->arrayNode('schedule')
                    ->info('Whether Symfony Scheduler takes the snapshots and settles the weeks due, on the schedule "tilttek_leaderboards".')
                    ->canBeEnabled()
                    ->children()
                        ->scalarNode('frequency')
                            ->info('How often the snapshots and settlements due are looked for, as Symfony Scheduler reads a frequency.')
                            ->cannotBeEmpty()
                            ->defaultValue('1 hour')
                        ->end()
                    ->end()
                ->end()
                ->scalarNode('secret')
                    ->info('The secret the luck of the teams is drawn from.')
                    ->cannotBeEmpty()
                    ->defaultValue('%kernel.secret%')
                ->end()
                ->scalarNode('kit_subdirectory')
                    ->info('Where the recipes of the kit are, under the components directory; the subdirectory of "ux_toolkit" when null. To set in an environment without the Toolkit.')
                    ->defaultNull()
                ->end()
                ->arrayNode('celebration')
                    ->info('The Celebration of the PHIL UX kit, played on the screen a member opens after reaching the top; its recipe is installed by the application.')
                    ->canBeDisabled()
                    ->children()
                        ->enumNode('effect')
                            ->info('What is drawn where motion is not reduced: "confetti" or "fireworks".')
                            ->values(['confetti', 'fireworks'])
                            ->defaultValue('confetti')
                        ->end()
                        ->enumNode('celebrate_on')
                            ->info('When a first place is told and celebrated: "period_close", for who finished first once the period closed; "score_change", for who takes the lead of the period under way as their score changes.')
                            ->values(array_map(static fn (CelebrateOn $case): string => $case->value, CelebrateOn::cases()))
                            ->defaultValue(CelebrateOn::PeriodClose->value)
                        ->end()
                    ->end()
                ->end()
                ->scalarNode('translation_domain')
                    ->info("The translation domain of the templates' texts and the boards' names (boards.<name>).")
                    ->cannotBeEmpty()
                    ->defaultValue('tilttek_leaderboards')
                ->end()
                ->arrayNode('privacy')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('member_kind')
                            ->info('The kind of the member reference a data subject of phil/privacy-bundle stands for.')
                            ->cannotBeEmpty()
                            ->defaultValue('member')
                        ->end()
                    ->end()
                ->end()
            ->end();
    }

    /**
     * @param array<array-key, mixed> $config
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        /** @var LeaderboardsConfig $config */
        LeaderboardsServices::register($builder, $config);

        // The privacy module, when `phil/privacy-bundle` is installed: a member's scores leave with their account.
        if (interface_exists(PersonalDataEraser::class)) {
            $builder->register(LeaderboardsPrivacy::class, LeaderboardsPrivacy::class)
                ->setArguments([new Reference(LeaderboardsServices::LEADERBOARDS), $config['privacy']['member_kind'], new Reference(LeadStore::class)])
                ->addTag(PhilPrivacyBundle::EXPORTER_TAG)
                ->addTag(PhilPrivacyBundle::ERASER_TAG);
        }
    }
}
