<?php

declare(strict_types=1);

namespace Tilttek\BadgesBundle\Infrastructure\DependencyInjection;

use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use Phil\OpenBadges\CredentialIssuer;
use Phil\OpenBadgesBundle\Application\Securing;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Uid\Uuid;
use Tilttek\Badges\BadgeProjection;
use Tilttek\Badges\Badges;
use Tilttek\Badges\Catalog;
use Tilttek\Badges\Evaluator;
use Tilttek\Badges\Measures;
use Tilttek\Badges\Store\AwardStore;
use Tilttek\Badges\Store\GrantStore;
use Tilttek\Badges\Store\InMemoryAwardStore;
use Tilttek\Badges\Store\InMemoryGrantStore;
use Tilttek\Badges\Store\InMemoryProfileStore;
use Tilttek\Badges\Store\InMemoryProgressStore;
use Tilttek\Badges\Store\ProfileStore;
use Tilttek\Badges\Store\ProgressStore;
use Tilttek\BadgesBundle\Infrastructure\Console\AwardCommand;
use Tilttek\BadgesBundle\Infrastructure\Console\ListCommand;
use Tilttek\BadgesBundle\Infrastructure\Console\RevokeCommand;
use Tilttek\BadgesBundle\Infrastructure\Doctrine\BadgesSchemaListener;
use Tilttek\BadgesBundle\Infrastructure\Doctrine\DbalAwardStore;
use Tilttek\BadgesBundle\Infrastructure\Doctrine\DbalGrantStore;
use Tilttek\BadgesBundle\Infrastructure\Doctrine\DbalProfileStore;
use Tilttek\BadgesBundle\Infrastructure\Doctrine\DbalProgressStore;
use Tilttek\BadgesBundle\Infrastructure\Event\DispatchingObserver;
use Tilttek\BadgesBundle\Infrastructure\OpenBadges\BadgeCredentials;
use Tilttek\BadgesBundle\Infrastructure\Twig\BadgesExtension;
use Twig\Attribute\AsTwigFunction;

/**
 * The bundle's services: the four stores on Doctrine DBAL or in memory, the catalog from the configuration — checked
 * when the container is compiled —, `Badges`, the projection tagged for `tilttek/activity-bundle` and telling the event
 * dispatcher, the Open Badges credentials on the services of `phil/open-badges-bundle` when they are enabled, and —
 * when their components are installed — the schema listener, the commands and the Twig functions.
 */
final class BadgesServices
{
    /**
     * The tag under which `tilttek/activity-bundle` finds its projections.
     */
    public const string PROJECTION_TAG = 'tilttek_activity.projection';

    /**
     * The name the projection's checkpoint is kept under, unless the configuration names another.
     */
    public const string DEFAULT_PROJECTION = BadgeProjection::NAME;

    /**
     * How many badges a member features, unless the configuration says otherwise.
     */
    public const int DEFAULT_SHOWCASE_SIZE = Badges::DEFAULT_SHOWCASE_SIZE;

    public const int DEFAULT_MAX_AWARDS_PER_ACTIVITY = BadgeProjection::MAX_AWARDS_PER_ACTIVITY;

    /**
     * The service of `Badges`, for the privacy module the bundle class registers.
     */
    public const string BADGES = Badges::class;

    /**
     * Whether `phil/open-badges-bundle` is registered, which the credentials need; set before the extension is loaded.
     */
    public const string OPEN_BADGES_PARAMETER = '.tilttek_badges.open_badges_bundle';

    private const array STORES = [
        ProgressStore::class => [InMemoryProgressStore::class, DbalProgressStore::class],
        AwardStore::class => [InMemoryAwardStore::class, DbalAwardStore::class],
        ProfileStore::class => [InMemoryProfileStore::class, DbalProfileStore::class],
        GrantStore::class => [InMemoryGrantStore::class, DbalGrantStore::class],
    ];

    private function __construct()
    {
    }

    /**
     * @param array{store: string, connection: string, timezone: string, badges: array<string, array<string, mixed>>, projection: string, showcase_size: int, max_awards_per_activity: int, translation_domain: string, open_badges: array{enabled: bool, achievement_url: ?string}, privacy: array{member_kind: string}} $config
     */
    public static function register(ContainerBuilder $container, array $config): void
    {
        Catalog::fromArray($config['badges'], $config['timezone']);
        $connection = new Reference(\sprintf('doctrine.dbal.%s_connection', $config['connection']));

        foreach (self::STORES as $port => [$memory, $dbal]) {
            $store = 'memory' === $config['store'] ? $memory : $dbal;
            $definition = $container->register($store, $store);

            if ('memory' === $config['store']) {
                $definition->addTag('kernel.reset', ['method' => 'reset']);
            } else {
                $definition->setArguments([$connection]);
            }

            $container->setAlias($port, $store)->setPublic(true);
        }

        if ('memory' !== $config['store'] && class_exists(GenerateSchemaEventArgs::class)) {
            $container->register(BadgesSchemaListener::class, BadgesSchemaListener::class)
                ->setArguments([$connection])
                ->addTag('doctrine.event_listener', ['event' => 'postGenerateSchema', 'connection' => $config['connection']]);
        }

        $container->register(Catalog::class, Catalog::class)
            ->setFactory([Catalog::class, 'fromArray'])
            ->setArguments([$config['badges'], $config['timezone']])
            ->setPublic(true);
        $container->register(Evaluator::class, Evaluator::class)
            ->setArguments([new Reference(Catalog::class), new Reference(ProgressStore::class), new Reference(Measures::class, ContainerInterface::NULL_ON_INVALID_REFERENCE)]);
        $container->register(Badges::class, Badges::class)
            ->setArguments([new Reference(Evaluator::class), new Reference(ProgressStore::class), new Reference(AwardStore::class), new Reference(ProfileStore::class), new Reference(GrantStore::class), new Reference('clock'), $config['showcase_size']])
            ->setPublic(true);
        $container->register(DispatchingObserver::class, DispatchingObserver::class)
            ->setArguments([new Reference(EventDispatcherInterface::class)]);
        $container->register(BadgeProjection::class, BadgeProjection::class)
            ->setArguments([new Reference(Evaluator::class), new Reference(ProgressStore::class), new Reference(AwardStore::class), new Reference(DispatchingObserver::class), $config['projection'], $config['max_awards_per_activity']])
            ->addTag(self::PROJECTION_TAG)
            ->addTag('kernel.reset', ['method' => 'clearReplay'])
            ->setPublic(true);

        if ($config['open_badges']['enabled']) {
            self::openBadges($container, $config['open_badges'], $config['translation_domain']);
        }

        if (class_exists(Command::class)) {
            foreach ([AwardCommand::class, RevokeCommand::class, ListCommand::class] as $command) {
                $container->register($command, $command)
                    ->setArguments([new Reference(Badges::class)])
                    ->addTag('console.command');
            }
        }

        if (class_exists(AsTwigFunction::class)) {
            $container->register(BadgesExtension::class, BadgesExtension::class)
                ->setArguments([new Reference(Badges::class), $config['translation_domain']])
                ->addTag('twig.attribute_extension')
                ->addTag('twig.runtime')
                ->setPublic(true);
        }
    }

    /**
     * @param array{enabled: bool, achievement_url: ?string} $config
     */
    private static function openBadges(ContainerBuilder $container, array $config, string $domain): void
    {
        if (true !== ($container->hasParameter(self::OPEN_BADGES_PARAMETER) ? $container->getParameter(self::OPEN_BADGES_PARAMETER) : false)) {
            throw new InvalidConfigurationException('Open Badges credentials are issued by phil/open-badges-bundle: register Phil\OpenBadgesBundle\PhilOpenBadgesBundle and configure phil_open_badges.');
        }

        if (null === $config['achievement_url']) {
            throw new InvalidConfigurationException('Open Badges credentials need the achievement URL: set tilttek_badges.open_badges.achievement_url.');
        }

        if (class_exists(Uuid::class)) {
            $container->register(BadgeCredentials::class, BadgeCredentials::class)
                ->setArguments([new Reference(Badges::class), new Reference(CredentialIssuer::class), new Reference(Securing::class), $config['achievement_url'], $domain, new Reference('translator', ContainerInterface::NULL_ON_INVALID_REFERENCE)])
                ->setPublic(true);
        }
    }
}
