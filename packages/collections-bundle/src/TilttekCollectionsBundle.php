<?php

declare(strict_types=1);

namespace Tilttek\CollectionsBundle;

use Override;
use Phil\PrivacyBundle\Application\Port\PersonalDataEraser;
use Phil\PrivacyBundle\PhilPrivacyBundle;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Tilttek\CollectionsBundle\Infrastructure\DependencyInjection\CollectionsServices;
use Tilttek\CollectionsBundle\Infrastructure\Privacy\CollectionsPrivacy;

/**
 * Collectible items for a Symfony application, on `tilttek/collections`:
 *
 * ```yaml
 * tilttek_collections:
 *     timezone: America/Toronto
 *     rarities: { common: 70, rare: 25, legendary: 5 }
 *     items:
 *         pirate: { name: { en: Pirate, fr: Pirate }, rarity: rare, sets: [crew], image: /img/pirate.webp }
 *         ember-d6: { name: Ember d6, rarity: common, sets: [ember], die: { sides: 6, color: '#c0392b' } }
 *     sets:
 *         crew: { name: The crew, rewards: [{ kind: points, code: xp, amount: 50 }] }
 *         ember: { name: Ember dice, kind: base }
 *     packs:
 *         booster: { name: Booster, size: 5 }
 *     daily:
 *         enabled: true
 *         verbs: ['page.viewed']
 *         sets: [crew]
 *         dates: { '10-31': pumpkin }
 *         computed: [{ rule: easter, item: egg }]
 * ```
 *
 * The configuration says where the collections are kept — a Doctrine DBAL connection, or memory for tests —, the
 * timezone of the calendar, the locale the dice are named in, whether the catalog is configured here or kept in the
 * database, the item of the day — the verbs that claim it, the salt of its draw, the sets it draws from, its fixed and
 * computed dates, the name of its projection —, the translation domain of the templates, and under which kind a data
 * subject is a member.
 */
final class TilttekCollectionsBundle extends AbstractBundle
{
    /** The shortest salt of the item of the day, in bytes. */
    public const int MIN_SALT = 32;

    #[Override]
    protected string $extensionAlias = 'tilttek_collections';

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $root = $definition->rootNode();
        $root
            ->validate()
                ->ifTrue(static fn (array $config): bool => 'doctrine' === $config['catalog'] && [[], [], [], []] !== [$config['rarities'], $config['items'], $config['sets'], $config['packs']])
                ->thenInvalid('The catalog is configured here or kept in the database, not both.')
            ->end()
            ->children()
                ->enumNode('store')
                    ->info('Where the collections are kept: "doctrine", or "memory" for tests.')
                    ->values(['doctrine', 'memory'])
                    ->defaultValue('doctrine')
                ->end()
                ->scalarNode('connection')
                    ->info('The Doctrine DBAL connection of the collections.')
                    ->cannotBeEmpty()
                    ->defaultValue('default')
                ->end()
                ->scalarNode('timezone')
                    ->info('The timezone of the calendar of the item of the day.')
                    ->cannotBeEmpty()
                    ->defaultValue('UTC')
                ->end()
                ->scalarNode('locale')
                    ->info('The locale the dice a member rolls are named in.')
                    ->cannotBeEmpty()
                    ->defaultValue('%kernel.default_locale%')
                ->end()
                ->enumNode('catalog')
                    ->info('Where the catalog is: "configuration", under rarities, items, sets and packs, or "doctrine", in the database.')
                    ->values(['configuration', 'doctrine'])
                    ->defaultValue('configuration')
                ->end()
            ->end();

        foreach (['rarities' => 'The rarities by code, from the most common to the rarest: a weight, or {weight}.', 'items' => 'The items by code: name, rarity, sets, description, image, tradable, die {sides, color, material}.', 'sets' => 'The sets by code: name, kind (plain, base, earned, paid), rewards.', 'packs' => 'The packs by code: name, size, sets, weights.'] as $name => $info) {
            $root->children()->append(new ArrayNodeDefinition($name)->info($info)->useAttributeAsKey('code')->normalizeKeys(false)->variablePrototype()->end());
        }

        $root
            ->children()
                ->arrayNode('daily')
                    ->addDefaultsIfNotSet()
                    ->validate()
                        ->ifTrue(static fn (array $daily): bool => $daily['enabled'] && [] === $daily['verbs'])
                        ->thenInvalid('The item of the day is claimed on at least one verb.')
                    ->end()
                    ->children()
                        ->booleanNode('enabled')->info('Whether the activity gives the item of the day.')->defaultFalse()->end()
                        ->arrayNode('verbs')->info('The verb patterns of the activity that counts as coming by.')->scalarPrototype()->end()->end()
                        ->scalarNode('salt')
                            ->info("The secret that makes the draw of a free day the site's own, at least 32 bytes; derived from kernel.secret when left out.")
                            ->defaultNull()
                            ->validate()
                                ->ifTrue(static fn (mixed $salt): bool => !\is_string($salt) || \strlen($salt) < self::MIN_SALT)
                                ->thenInvalid('The salt of the item of the day is a secret of at least 32 bytes, such as "%%env(COLLECTIONS_DAILY_SALT)%%"; %s is not.')
                            ->end()
                        ->end()
                        ->arrayNode('sets')->info('The sets the free days draw from; every item when none is named.')->scalarPrototype()->end()->end()
                        ->arrayNode('dates')->info('Items on fixed dates: "10-31" every year, "2026-12-25" once.')->useAttributeAsKey('date')->normalizeKeys(false)->scalarPrototype()->end()->end()
                        ->arrayNode('computed')
                            ->info('Items on the dates phil/almanac computes: {rule, item, days}.')
                            ->arrayPrototype()
                                ->children()
                                    ->scalarNode('rule')->isRequired()->cannotBeEmpty()->end()
                                    ->scalarNode('item')->isRequired()->cannotBeEmpty()->end()
                                    ->integerNode('days')->min(1)->max(366)->defaultValue(1)->end()
                                ->end()
                            ->end()
                        ->end()
                        ->scalarNode('projection')->info('The name of the projection that gives the item of the day.')->cannotBeEmpty()->defaultValue(CollectionsServices::DEFAULT_PROJECTION)->end()
                    ->end()
                ->end()
                ->scalarNode('translation_domain')
                    ->info("The translation domain of the templates' texts.")
                    ->cannotBeEmpty()
                    ->defaultValue('tilttek_collections')
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
        /** @var array{store: string, connection: string, timezone: string, locale: string, catalog: string, rarities: array<string, mixed>, items: array<string, mixed>, sets: array<string, mixed>, packs: array<string, mixed>, daily: array{enabled: bool, verbs: list<string>, salt: ?string, sets: list<string>, dates: array<string, string>, computed: list<array{rule: string, item: string, days: int}>, projection: string}, translation_domain: string, privacy: array{member_kind: string}} $config */
        CollectionsServices::register($builder, $config);

        // The privacy module, when `phil/privacy-bundle` is installed: a member's collection leaves with their account.
        if (interface_exists(PersonalDataEraser::class)) {
            $builder->register(CollectionsPrivacy::class, CollectionsPrivacy::class)
                ->setArguments([new Reference(CollectionsServices::MEMBER_DATA), $config['privacy']['member_kind']])
                ->addTag(PhilPrivacyBundle::EXPORTER_TAG)
                ->addTag(PhilPrivacyBundle::ERASER_TAG);
        }
    }
}
