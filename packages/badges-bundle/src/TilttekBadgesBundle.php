<?php

declare(strict_types=1);

namespace Tilttek\BadgesBundle;

use Override;
use Phil\PrivacyBundle\Application\Port\PersonalDataEraser;
use Phil\PrivacyBundle\PhilPrivacyBundle;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Tilttek\BadgesBundle\Infrastructure\DependencyInjection\BadgesServices;
use Tilttek\BadgesBundle\Infrastructure\Privacy\BadgesPrivacy;

/**
 * Badges for a Symfony application, on `tilttek/badges`:
 *
 * ```yaml
 * tilttek_badges:
 *     badges:
 *         commenter.bronze:
 *             name: badges.commenter.bronze.name
 *             tier: { series: commenter, rank: 1 }
 *             criterion: { count: { verbs: ['news.commented'], times: 10 } }
 *         organiser: { name: badges.organiser.name, criterion: { manual: true } }
 * ```
 *
 * The configuration says where the badges are kept — a Doctrine DBAL connection, or memory for tests —, the badges and
 * their criteria, the timezone their days are counted in, the name of the projection that `tilttek/activity-bundle`
 * catches up, the size of a showcase, the translation domain of the badges' texts, the identifier of a badge in its
 * Open Badges credentials — whose issuer and key are `phil/open-badges-bundle`'s —, and under which kind a data
 * subject is a member.
 */
final class TilttekBadgesBundle extends AbstractBundle
{
    #[Override]
    protected string $extensionAlias = 'tilttek_badges';

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->enumNode('store')
                    ->info('Where the badges are kept: "doctrine", or "memory" for tests.')
                    ->values(['doctrine', 'memory'])
                    ->defaultValue('doctrine')
                ->end()
                ->scalarNode('connection')
                    ->info('The Doctrine DBAL connection of the badges.')
                    ->cannotBeEmpty()
                    ->defaultValue('default')
                ->end()
                ->scalarNode('timezone')
                    ->info('The timezone the days of a streak and the dates of a period are read in.')
                    ->cannotBeEmpty()
                    ->defaultValue('UTC')
                ->end()
                ->arrayNode('badges')
                    ->info('The badges by code: name, description, image, repeatable, hidden, revocable, tier {series, rank}, and the criterion — count, total, first, streak, measure, manual, all or at_least.')
                    ->useAttributeAsKey('code')
                    ->variablePrototype()->end()
                ->end()
                ->scalarNode('projection')
                    ->info('The name of the projection that awards the badges.')
                    ->cannotBeEmpty()
                    ->defaultValue(BadgesServices::DEFAULT_PROJECTION)
                ->end()
                ->integerNode('showcase_size')
                    ->info('How many badges a member features on their profile, at most.')
                    ->min(1)
                    ->defaultValue(BadgesServices::DEFAULT_SHOWCASE_SIZE)
                ->end()
                ->integerNode('max_awards_per_activity')
                    ->info('How many awards of a repeatable badge one activity brings, at most; the awards still due come with the next activities.')
                    ->min(1)
                    ->defaultValue(BadgesServices::DEFAULT_MAX_AWARDS_PER_ACTIVITY)
                ->end()
                ->scalarNode('translation_domain')
                    ->info("The translation domain of the badges' names and descriptions.")
                    ->cannotBeEmpty()
                    ->defaultValue('badges')
                ->end()
                ->arrayNode('open_badges')
                    ->info('The Open Badges 3.0 credentials a member asks for, issued and secured by phil/open-badges-bundle.')
                    ->canBeEnabled()
                    ->children()
                        ->scalarNode('achievement_url')
                            ->info('The identifier of a badge as an achievement, an absolute URL where "{code}" stands for its code.')
                            ->defaultNull()
                        ->end()
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

    #[Override]
    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $builder->setParameter(BadgesServices::OPEN_BADGES_PARAMETER, $builder->hasExtension('phil_open_badges'));
    }

    /**
     * @param array<array-key, mixed> $config
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        /** @var array{store: string, connection: string, timezone: string, badges: array<string, array<string, mixed>>, projection: string, showcase_size: int, max_awards_per_activity: int, translation_domain: string, open_badges: array{enabled: bool, achievement_url: ?string}, privacy: array{member_kind: string}} $config */
        BadgesServices::register($builder, $config);

        // The privacy module, when `phil/privacy-bundle` is installed: a member's badges leave with their account.
        if (interface_exists(PersonalDataEraser::class)) {
            $builder->register(BadgesPrivacy::class, BadgesPrivacy::class)
                ->setArguments([new Reference(BadgesServices::BADGES), $config['privacy']['member_kind']])
                ->addTag(PhilPrivacyBundle::EXPORTER_TAG)
                ->addTag(PhilPrivacyBundle::ERASER_TAG);
        }
    }
}
