<?php

declare(strict_types=1);

namespace Tilttek\AdventCalendarBundle;

use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Override;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Tilttek\AdventCalendar\Exception\InvalidCalendar;
use Tilttek\AdventCalendar\PolicyKind;
use Tilttek\AdventCalendar\Surprise;
use Tilttek\AdventCalendar\SurpriseKind;
use Tilttek\AdventCalendarBundle\Application\ConfiguredCalendars;
use Tilttek\AdventCalendarBundle\Infrastructure\DependencyInjection\AdventCalendarServices;
use Tilttek\AdventCalendarBundle\Infrastructure\DependencyInjection\KitSubdirectoryPass;

/**
 * Advent calendars in a Symfony application, on `tilttek/advent-calendar`:
 *
 * ```yaml
 * tilttek_advent_calendar:
 *     member_resolver: App\Advent\CurrentMember
 *     layout: base.html.twig
 *     celebration: { enabled: true, effect: confetti }
 *     calendars:
 *         advent-2026:
 *             title: Advent 2026
 *             timezone: America/Toronto
 *             first_date: '2026-12-01'
 *             surprises: [{ kind: points, quantity: 10 }, { kind: badge, reference: advent-week }]
 * ```
 *
 * The configuration says where the openings are kept — a Doctrine DBAL connection, or memory for tests —, the entity
 * manager of the calendars the site edits, which service names the current member, the layout the screen extends,
 * where the recipes of the UX kit are, whether the kit's celebration plays for a first opening, the calendars declared
 * here, and the kind of reference a data subject of `phil/privacy-bundle` stands for.
 *
 * @phpstan-import-type AdventCalendarConfig from AdventCalendarServices
 * @phpstan-import-type CalendarConfig from ConfiguredCalendars
 */
final class TilttekAdventCalendarBundle extends AbstractBundle
{
    #[Override]
    protected string $extensionAlias = 'tilttek_advent_calendar';

    #[Override]
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new KitSubdirectoryPass());
    }

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $kinds = array_map(static fn (SurpriseKind $kind): string => $kind->value, SurpriseKind::cases());

        $definition->rootNode()
            ->children()
                ->enumNode('store')
                    ->info('Where the openings are kept: "doctrine", or "memory" for tests.')
                    ->values(['doctrine', 'memory'])
                    ->defaultValue('doctrine')
                ->end()
                ->scalarNode('connection')
                    ->info('The Doctrine DBAL connection of the openings.')
                    ->cannotBeEmpty()
                    ->defaultValue('default')
                ->end()
                ->scalarNode('entity_manager')
                    ->info('The Doctrine entity manager of the calendars the site edits.')
                    ->cannotBeEmpty()
                    ->defaultValue('default')
                ->end()
                ->scalarNode('member_resolver')
                    ->info('The service implementing MemberResolver, which names the current member by a stable identifier; without it, every viewer is a visitor who opens nothing.')
                    ->defaultNull()
                ->end()
                ->scalarNode('layout')
                    ->info('The template the screen extends; it fills its block "body".')
                    ->cannotBeEmpty()
                    ->defaultValue('base.html.twig')
                ->end()
                ->scalarNode('kit_subdirectory')
                    ->info('Where the recipes of the kit are, under the components directory; the subdirectory of "ux_toolkit" when null.')
                    ->defaultNull()
                ->end()
                ->arrayNode('celebration')
                    ->info('The Celebration of the PHIL UX kit, played for a box opened for the first time; its recipe is installed by the application.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')->info('Whether the screen plays it.')->defaultFalse()->end()
                        ->enumNode('effect')->info('What it draws where motion is not reduced.')->values(['confetti', 'fireworks'])->defaultValue('confetti')->end()
                    ->end()
                ->end()
                ->arrayNode('calendars')
                    ->info('Calendars declared here, by identifier; they hide a calendar of the database with the same identifier.')
                    ->useAttributeAsKey('id')
                    ->normalizeKeys(false)
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('title')->isRequired()->cannotBeEmpty()->end()
                            ->scalarNode('timezone')->isRequired()->cannotBeEmpty()->end()
                            ->scalarNode('first_date')->info('The date of box 1, "2026-12-01".')->isRequired()->cannotBeEmpty()->end()
                            ->scalarNode('closing_date')->info('The last date a box may be opened; the date of the last box when null.')->defaultNull()->end()
                            ->enumNode('policy')
                                ->info('How long a box stays openable once its day has come.')
                                ->values(array_map(static fn (PolicyKind $kind): string => $kind->value, PolicyKind::cases()))
                                ->defaultValue(PolicyKind::UntilClosing->value)
                            ->end()
                            ->integerNode('catch_up_days')->info('The days of a catch-up policy, 1 to 31.')->defaultValue(0)->end()
                            ->booleanNode('published')->defaultTrue()->end()
                            ->arrayNode('surprises')
                                ->info('One per box, in the order of the days.')
                                ->isRequired()
                                ->requiresAtLeastOneElement()
                                ->arrayPrototype()
                                    ->children()
                                        ->enumNode('kind')->values($kinds)->isRequired()->end()
                                        ->scalarNode('reference')->info('The ledger, badge, content or pool; "points" for points when left out.')->defaultValue('points')->end()
                                        ->integerNode('quantity')->min(1)->max(Surprise::MAX_QUANTITY)->defaultValue(1)->end()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                    ->validate()
                        ->always($this->checked(...))
                    ->end()
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
     * The calendars of the configuration, each checked by the library: one that does not hold together stops the
     * container from being built, with the library's reason.
     *
     * @param array<string, CalendarConfig> $calendars
     *
     * @return array<string, CalendarConfig>
     */
    private function checked(array $calendars): array
    {
        foreach ($calendars as $id => $calendar) {
            try {
                ConfiguredCalendars::calendar($id, $calendar);
            } catch (InvalidCalendar $invalidCalendar) {
                throw new InvalidArgumentException($invalidCalendar->getMessage(), 0, $invalidCalendar);
            }
        }

        return $calendars;
    }

    #[Override]
    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        if ($builder->hasExtension('doctrine') && interface_exists(EntityManagerInterface::class)) {
            $container->extension('doctrine', ['orm' => ['mappings' => ['TilttekAdventCalendar' => [
                'type' => 'attribute',
                'is_bundle' => false,
                'dir' => __DIR__.'/Domain/Model',
                'prefix' => 'Tilttek\AdventCalendarBundle\Domain\Model',
            ]]]]);
        }
    }

    /**
     * @param array<array-key, mixed> $config the configuration, processed by the tree above
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        /** @var AdventCalendarConfig $config */
        AdventCalendarServices::register($builder, $config);
    }
}
