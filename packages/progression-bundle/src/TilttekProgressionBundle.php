<?php

declare(strict_types=1);

namespace Tilttek\ProgressionBundle;

use Override;
use Phil\PrivacyBundle\Application\Port\PersonalDataEraser;
use Phil\PrivacyBundle\PhilPrivacyBundle;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Tilttek\ProgressionBundle\Infrastructure\DependencyInjection\ProgressionServices;
use Tilttek\ProgressionBundle\Infrastructure\Privacy\ProgressionPrivacy;

/**
 * Levels and streaks for a Symfony application, on `tilttek/progression`:
 *
 * ```yaml
 * tilttek_progression:
 *     timezone: America/Toronto
 *     tracks:
 *         player:
 *             curve: { type: quadratic, base: 50, levels: 60 }
 *             seasons: { length: quarter }
 *             rules: { games: { verbs: ['game.played'], experience: 20, cap: { amount: 100, period: day } } }
 *             milestones: { veteran: { level: 10 } }
 *     streaks:
 *         daily-play: { verbs: ['game.played'], grace: { allowed: 1, window: 7 } }
 *     timezone_context: timezone
 * ```
 *
 * The configuration says where the progression is kept — a Doctrine DBAL connection, or memory for tests —, the
 * tracks with their curves, rules, seasons and milestones, the streaks, the timezone of caps, seasons and members' days
 * — and the context key that carries a member's own —, the name of the projection that `tilttek/activity-bundle`
 * catches up, the translation domain of the templates, and under which kind a data subject is a member.
 */
final class TilttekProgressionBundle extends AbstractBundle
{
    #[Override]
    protected string $extensionAlias = 'tilttek_progression';

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->enumNode('store')
                    ->info('Where the progression is kept: "doctrine", or "memory" for tests.')
                    ->values(['doctrine', 'memory'])
                    ->defaultValue('doctrine')
                ->end()
                ->scalarNode('connection')
                    ->info('The Doctrine DBAL connection of the progression.')
                    ->cannotBeEmpty()
                    ->defaultValue('default')
                ->end()
                ->scalarNode('timezone')
                    ->info("The timezone of the caps' periods, of the calendar seasons, and of a member's days when nothing says theirs.")
                    ->cannotBeEmpty()
                    ->defaultValue('UTC')
                ->end()
                ->arrayNode('tracks')
                    ->info('The tracks by name: the curve of their levels, whom their experience goes to, their seasons, the rules that give it and the milestones worth a reward.')
                    ->useAttributeAsKey('name')
                    ->normalizeKeys(false)
                    ->arrayPrototype()
                        ->children()
                            ->arrayNode('curve')
                                ->info('"linear" with its step, "quadratic" or "exponential" with their base — and the factor of the second —, each with its number of levels; or a "table" of the thresholds of levels 2, 3…')
                                ->isRequired()
                                ->children()
                                    ->enumNode('type')->isRequired()->values(['linear', 'quadratic', 'exponential', 'table'])->end()
                                    ->integerNode('step')->min(1)->end()
                                    ->integerNode('base')->min(1)->end()
                                    ->floatNode('factor')->end()
                                    ->integerNode('levels')->min(2)->end()
                                    ->arrayNode('thresholds')->integerPrototype()->end()->end()
                                ->end()
                            ->end()
                            ->enumNode('subject')
                                ->info('Whom the experience goes to: the member who made the gesture, or its object, such as a character.')
                                ->values(['member', 'object'])
                                ->defaultValue('member')
                            ->end()
                            ->arrayNode('seasons')
                                ->info('Seasons that follow the calendar by "length" — month, quarter or year —, or "named" ones with their code, start and end.')
                                ->children()
                                    ->enumNode('length')->values(['month', 'quarter', 'year'])->end()
                                    ->arrayNode('named')
                                        ->arrayPrototype()
                                            ->children()
                                                ->scalarNode('code')->isRequired()->end()
                                                ->scalarNode('start')->isRequired()->end()
                                                ->scalarNode('end')->isRequired()->end()
                                            ->end()
                                        ->end()
                                    ->end()
                                ->end()
                            ->end()
                            ->arrayNode('rules')
                                ->info('The rules by name: the verbs they read, the experience per unit, whether the quantity counts, the cap per subject and period, the context the activity must hold.')
                                ->useAttributeAsKey('name')
                    ->normalizeKeys(false)
                                ->arrayPrototype()
                                    ->children()
                                        ->arrayNode('verbs')
                                            ->isRequired()
                                            ->requiresAtLeastOneElement()
                                            ->scalarPrototype()->end()
                                        ->end()
                                        ->integerNode('experience')->isRequired()->min(1)->end()
                                        ->booleanNode('per_quantity')->defaultTrue()->end()
                                        ->arrayNode('cap')
                                            ->children()
                                                ->integerNode('amount')->isRequired()->min(1)->end()
                                                ->enumNode('period')->isRequired()->values(['day', 'week', 'month', 'year', 'ever'])->end()
                                            ->end()
                                        ->end()
                                        ->append($this->context())
                                    ->end()
                                ->end()
                            ->end()
                            ->arrayNode('milestones')
                                ->info('The milestones by code, each at an amount of "experience" or at a "level".')
                                ->useAttributeAsKey('code')
                    ->normalizeKeys(false)
                                ->arrayPrototype()
                                    ->children()
                                        ->integerNode('experience')->min(1)->end()
                                        ->integerNode('level')->min(2)->end()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('streaks')
                    ->info('The streaks by name: the verbs they read, the unit (day or week), the grace {allowed, window}, the context the activity must hold.')
                    ->useAttributeAsKey('name')
                    ->normalizeKeys(false)
                    ->arrayPrototype()
                        ->children()
                            ->arrayNode('verbs')
                                ->isRequired()
                                ->requiresAtLeastOneElement()
                                ->scalarPrototype()->end()
                            ->end()
                            ->enumNode('unit')->values(['day', 'week'])->defaultValue('day')->end()
                            ->arrayNode('grace')
                                ->children()
                                    ->integerNode('allowed')->isRequired()->min(1)->end()
                                    ->integerNode('window')->isRequired()->min(2)->end()
                                ->end()
                            ->end()
                            ->append($this->context())
                        ->end()
                    ->end()
                ->end()
                ->scalarNode('timezone_context')
                    ->info("The key of the activity's context that holds the member's timezone, such as \"timezone\"; null to read every member's days in the configured timezone.")
                    ->defaultNull()
                ->end()
                ->scalarNode('projection')
                    ->info('The name of the projection that turns the activity into experience and streaks.')
                    ->cannotBeEmpty()
                    ->defaultValue(ProgressionServices::DEFAULT_PROJECTION)
                ->end()
                ->scalarNode('translation_domain')
                    ->info("The translation domain of the templates' texts, the tracks' names (tracks.<name>) and the streaks' names (streaks.<name>).")
                    ->cannotBeEmpty()
                    ->defaultValue('tilttek_progression')
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
     * The facts an activity's context must hold: integers, booleans or strings, by name.
     */
    private function context(): ArrayNodeDefinition
    {
        return new ArrayNodeDefinition('context')
            ->useAttributeAsKey('name')
            ->scalarPrototype()->end()
            ->validate()
                ->ifTrue(static fn (array $context): bool => array_any($context, static fn (mixed $value): bool => !\is_bool($value) && !\is_int($value) && !\is_string($value)))
                ->thenInvalid('A context condition is an integer, a boolean or a string.')
            ->end();
    }

    /**
     * @param array<array-key, mixed> $config
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        /** @var array{store: string, connection: string, timezone: string, tracks: array<string, array{curve: array{type: string, step?: int, base?: int, factor?: float|int, levels?: int, thresholds?: list<int>}, subject: string, seasons?: array{length?: ?string, named?: list<array{code: string, start: string, end: string}>}, rules: array<string, array{verbs: list<string>, experience: int, per_quantity: bool, cap?: array{amount: int, period: string}, context: array<string, bool|int|string>}>, milestones: array<string, array{experience?: int, level?: int}>}>, streaks: array<string, array{verbs: list<string>, unit: string, grace?: array{allowed: int, window: int}, context: array<string, bool|int|string>}>, timezone_context: ?string, projection: string, translation_domain: string, privacy: array{member_kind: string}} $config */
        ProgressionServices::register($builder, $config);

        // The privacy module, when `phil/privacy-bundle` is installed: a member's progression leaves with their account.
        if (interface_exists(PersonalDataEraser::class)) {
            $builder->register(ProgressionPrivacy::class, ProgressionPrivacy::class)
                ->setArguments([new Reference(ProgressionServices::PROGRESSION), $config['privacy']['member_kind']])
                ->addTag(PhilPrivacyBundle::EXPORTER_TAG)
                ->addTag(PhilPrivacyBundle::ERASER_TAG);
        }
    }
}
