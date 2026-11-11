<?php

declare(strict_types=1);

namespace Tilttek\QuestsBundle;

use DateInterval;
use DateTimeImmutable;
use Exception;
use Override;
use Phil\PrivacyBundle\Application\Port\PersonalDataEraser;
use Phil\PrivacyBundle\PhilPrivacyBundle;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Tilttek\QuestsBundle\Infrastructure\DependencyInjection\QuestsServices;
use Tilttek\QuestsBundle\Infrastructure\Privacy\QuestsPrivacy;

/**
 * Quests and challenges for a Symfony application, on `tilttek/quests`:
 *
 * ```yaml
 * tilttek_quests:
 *     timezone: America/Toronto
 *     quests:
 *         first-steps:
 *             steps: [{ code: play, verbs: ['game.played'], count: 3 }, { code: write, verbs: ['sheet.published'] }]
 *             rewards: [{ kind: points, code: xp, amount: 50 }]
 *         winter-reading:
 *             admission: accept
 *             recurs: { from: '12-21', until: '03-20' }
 *             steps: [{ verbs: ['book.read'], total: 300 }]
 *         thousand-games: { kind: community, opens: '2026-12-01', closes: '2027-01-01', steps: [{ verbs: ['game.played'], count: 1000 }] }
 *         ten-games: { kind: duel, duel: { length: P7D }, steps: [{ verbs: ['game.played'], count: 10 }] }
 * ```
 *
 * The configuration says where the quests' progress is kept — a Doctrine DBAL connection, or memory for tests —, the
 * timezone their dates are read in, whether the quests are configured here or kept in the database, how long a duel
 * offer waits for its answer, the name of the projection that `tilttek/activity-bundle` catches up, the translation
 * domain of the templates, and under which kind a data subject is a member.
 */
final class TilttekQuestsBundle extends AbstractBundle
{
    #[Override]
    protected string $extensionAlias = 'tilttek_quests';

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->validate()
                ->ifTrue(static fn (array $config): bool => 'doctrine' === $config['catalog'] && [] !== $config['quests'])
                ->thenInvalid('The quests are configured here or kept in the database, not both.')
            ->end()
            ->children()
                ->enumNode('store')
                    ->info('Where the progress is kept: "doctrine", or "memory" for tests.')
                    ->values(['doctrine', 'memory'])
                    ->defaultValue('doctrine')
                ->end()
                ->scalarNode('connection')
                    ->info('The Doctrine DBAL connection of the quests.')
                    ->cannotBeEmpty()
                    ->defaultValue('default')
                ->end()
                ->scalarNode('timezone')
                    ->info("The timezone the quests' dates are read in.")
                    ->cannotBeEmpty()
                    ->defaultValue('UTC')
                ->end()
                ->enumNode('catalog')
                    ->info('Where the quests are: "configuration", under quests, or "doctrine", in the database.')
                    ->values(['configuration', 'doctrine'])
                    ->defaultValue('configuration')
                ->end()
                ->arrayNode('quests')
                    ->info('The quests by code: kind, steps, ordered, admission, opens and closes or recurs, after, rewards, duel {length}.')
                    ->useAttributeAsKey('code')
                    ->normalizeKeys(false)
                    ->variablePrototype()->end()
                ->end()
                ->arrayNode('duels')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('offer_lifetime')
                            ->info('How long a duel offer waits for its answer, as an ISO 8601 duration.')
                            ->cannotBeEmpty()
                            ->defaultValue('P7D')
                            ->validate()
                                ->ifTrue(static fn (mixed $value): bool => !self::isDuration($value))
                                ->thenInvalid('%s is not an ISO 8601 duration of some time, like "P7D".')
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->scalarNode('projection')
                    ->info('The name of the projection that turns the activity into progress.')
                    ->cannotBeEmpty()
                    ->defaultValue(QuestsServices::DEFAULT_PROJECTION)
                ->end()
                ->scalarNode('translation_domain')
                    ->info("The translation domain of the templates' texts and of the quests' names (quests.<code>.name).")
                    ->cannotBeEmpty()
                    ->defaultValue('tilttek_quests')
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
        /** @var array{store: string, connection: string, timezone: string, catalog: string, quests: array<string, array<array-key, mixed>>, duels: array{offer_lifetime: string}, projection: string, translation_domain: string, privacy: array{member_kind: string}} $config */
        QuestsServices::register($builder, $config);

        // The privacy module, when `phil/privacy-bundle` is installed: a member's quests leave with their account.
        if (interface_exists(PersonalDataEraser::class)) {
            $builder->register(QuestsPrivacy::class, QuestsPrivacy::class)
                ->setArguments([new Reference(QuestsServices::QUESTS), $config['privacy']['member_kind']])
                ->addTag(PhilPrivacyBundle::EXPORTER_TAG)
                ->addTag(PhilPrivacyBundle::ERASER_TAG);
        }
    }

    private static function isDuration(mixed $value): bool
    {
        if (!\is_string($value)) {
            return false;
        }

        try {
            $now = new DateTimeImmutable();

            return $now->add(new DateInterval($value)) > $now;
        } catch (Exception) {
            return false;
        }
    }
}
