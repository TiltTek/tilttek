<?php

declare(strict_types=1);

namespace Tilttek\EscapeRoomBundle;

use Override;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Tilttek\EscapeRoom\Room;
use Tilttek\EscapeRoomBundle\Infrastructure\DependencyInjection\EscapeRoomServices;
use Tilttek\EscapeRoomBundle\Infrastructure\DependencyInjection\KitSubdirectoryPass;
use Tilttek\EscapeRoomBundle\Infrastructure\DependencyInjection\MercureHubPass;

/**
 * Escape rooms for a Symfony application, on `tilttek/escape-room`:
 *
 * ```yaml
 * tilttek_escape_room:
 *     access: App\EscapeRoom\Access
 *     rooms:
 *         crypt:
 *             title: The crypt
 *             duration: 3600
 *             hint_penalty: 120
 *             scenario: { id: crypt, finale: tomb, puzzles: [{ id: candle, title: The candle, answers: [wick] }, …] }
 *     schedule: { enabled: true }
 * ```
 *
 * The configuration says where the games are kept — a Doctrine DBAL connection, or memory for tests —, the rooms or
 * the application's catalog of them, who runs and watches a game, how the teams are named on the leaderboard, the
 * reservations of the sessions and the rules of the rooms on `phil/reservation`, the limit of the guesses, whether the
 * games whose countdown reached zero are ended from Symfony Scheduler, the Mercure hub of the screens, the layout of
 * the screens, the size of the leaderboard, the celebration of an escape, the translation domain, and under which kind
 * a data subject is a member.
 *
 * @phpstan-import-type EscapeRoomConfig from EscapeRoomServices
 */
final class TilttekEscapeRoomBundle extends AbstractBundle
{
    #[Override]
    protected string $extensionAlias = 'tilttek_escape_room';

    #[Override]
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new KitSubdirectoryPass());
        $container->addCompilerPass(new MercureHubPass());
    }

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->enumNode('store')
                    ->info('Where the games, the results and the screens are kept: "doctrine", or "memory" for tests.')
                    ->values(['doctrine', 'memory'])
                    ->defaultValue('doctrine')
                ->end()
                ->scalarNode('connection')
                    ->info('The Doctrine DBAL connection of the escape rooms.')
                    ->cannotBeEmpty()
                    ->defaultValue('default')
                ->end()
                ->arrayNode('rooms')
                    ->info('The rooms by identifier: their title, time, penalties, team sizes and scenario of puzzles.')
                    ->useAttributeAsKey('id')
                    ->normalizeKeys(false)
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('title')->info('The title of the room, 1 to 200 characters.')->isRequired()->cannotBeEmpty()->end()
                            ->integerNode('duration')->info('The seconds a team has, 60 to 86 400.')->min(Room::MIN_DURATION)->max(Room::MAX_DURATION)->defaultValue(3_600)->end()
                            ->integerNode('hint_penalty')->info('The seconds a hint without a penalty of its own adds to the final time.')->min(0)->max(Room::MAX_PENALTY)->defaultValue(0)->end()
                            ->integerNode('skip_penalty')->info('The seconds a puzzle skipped by the host adds to the final time.')->min(0)->max(Room::MAX_PENALTY)->defaultValue(0)->end()
                            ->integerNode('min_players')->info('The smallest team.')->min(1)->max(Room::MAX_PLAYERS)->defaultValue(2)->end()
                            ->integerNode('max_players')->info('The largest team: the seats of a session.')->min(1)->max(Room::MAX_PLAYERS)->defaultValue(6)->end()
                            ->variableNode('scenario')
                                ->info('The scenario of the room, as tilttek/puzzle-builder reads it: its id, finale and puzzles, each with its id, title, answers, hints and unlock.')
                                ->isRequired()
                                ->validate()
                                    ->ifTrue(static fn (mixed $scenario): bool => !\is_array($scenario))
                                    ->thenInvalid('The scenario of a room is a map of its fields.')
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->scalarNode('room_catalog')
                    ->info('The service of a Tilttek\EscapeRoomBundle\Application\Contract\RoomCatalog that holds the rooms instead of the configuration.')
                    ->defaultNull()
                ->end()
                ->scalarNode('access')
                    ->info('The service of a Tilttek\EscapeRoomBundle\Application\Contract\EscapeRoomAccess that says who runs, watches and speaks for a game; the console, the screen and the consent are refused without it.')
                    ->defaultNull()
                ->end()
                ->scalarNode('team_names')
                    ->info('The service of a Tilttek\EscapeRoomBundle\Application\Contract\TeamNames that names the teams on the leaderboard; each is named by its place without it.')
                    ->defaultNull()
                ->end()
                ->scalarNode('reservations')
                    ->info('The service of a Tilttek\EscapeRoom\Contract\SessionReservations of the application; without it, the reservations on phil/reservation when it is installed, the memory store otherwise.')
                    ->defaultNull()
                ->end()
                ->scalarNode('room_bookables')
                    ->info('The service of a Tilttek\EscapeRoom\Reservation\Contract\RoomBookables that gives the opening hours and rules of each room on phil/reservation.')
                    ->defaultNull()
                ->end()
                ->arrayNode('guesses')
                    ->info('How many guesses a team makes on a puzzle in a window of seconds.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->integerNode('limit')->min(1)->max(1_000)->defaultValue(10)->end()
                        ->integerNode('seconds')->min(1)->max(86_400)->defaultValue(60)->end()
                    ->end()
                ->end()
                ->scalarNode('guess_limiter')
                    ->info('The service of a Tilttek\PuzzleBuilder\Contract\GuessLimiter used instead of the limit above.')
                    ->defaultNull()
                ->end()
                ->arrayNode('schedule')
                    ->info('Whether Symfony Scheduler ends the games whose countdown reached zero, on the schedule "tilttek_escape_room".')
                    ->canBeEnabled()
                    ->children()
                        ->scalarNode('frequency')
                            ->info('How often the games due are looked for, as Symfony Scheduler reads a frequency.')
                            ->cannotBeEmpty()
                            ->defaultValue('15 seconds')
                        ->end()
                        ->integerNode('batch')
                            ->info('How many games one run ends at most.')
                            ->min(1)
                            ->max(1_000)
                            ->defaultValue(100)
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('mercure')
                    ->info('The Mercure hub the screens are told of each move through; they read the game again at intervals without one.')
                    ->canBeDisabled()
                    ->children()
                        ->scalarNode('hub')
                            ->info('The service of the hub; the default hub of the application when Mercure is installed, if null.')
                            ->defaultNull()
                        ->end()
                        ->scalarNode('hub_name')
                            ->info('The name of the hub of MercureBundle whose authorization cookie the viewers are given.')
                            ->defaultNull()
                        ->end()
                        ->scalarNode('topic_prefix')
                            ->info('The prefix of the topic of each game.')
                            ->cannotBeEmpty()
                            ->defaultValue('tilttek-escape-room')
                        ->end()
                        ->integerNode('poll')
                            ->info('The seconds between two readings of a screen when no hub tells it of the moves.')
                            ->min(1)
                            ->max(300)
                            ->defaultValue(5)
                        ->end()
                    ->end()
                ->end()
                ->scalarNode('layout')
                    ->info('The template of the application the screens extend; it has a block "body".')
                    ->cannotBeEmpty()
                    ->defaultValue('base.html.twig')
                ->end()
                ->integerNode('leaderboard_size')
                    ->info('How many places the public leaderboard of a room shows.')
                    ->min(1)
                    ->max(1_000)
                    ->defaultValue(10)
                ->end()
                ->scalarNode('kit_subdirectory')
                    ->info('Where the recipes of the kit are, under the components directory; the subdirectory of "ux_toolkit" when null. To set in an environment without the Toolkit.')
                    ->defaultNull()
                ->end()
                ->arrayNode('celebration')
                    ->info('The Celebration of the PHIL UX kit, played on the hint screen when the team escapes; its recipe is installed by the application.')
                    ->canBeDisabled()
                    ->children()
                        ->enumNode('effect')
                            ->info('What is drawn where motion is not reduced: "confetti" or "fireworks".')
                            ->values(['confetti', 'fireworks'])
                            ->defaultValue('confetti')
                        ->end()
                    ->end()
                ->end()
                ->scalarNode('translation_domain')
                    ->info("The translation domain of the screens' texts.")
                    ->cannotBeEmpty()
                    ->defaultValue('tilttek_escape_room')
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
        /** @var EscapeRoomConfig $config */
        EscapeRoomServices::register($builder, $config);
    }
}
