<?php

declare(strict_types=1);

namespace Tilttek\BadgesBundle\Tests\Unit;

use LogicException;
use Phil\OpenBadges\CredentialIssuer;
use Phil\OpenBadgesBundle\Application\Securing;
use Phil\OpenBadgesBundle\PhilOpenBadgesBundle;
use Phil\PrivacyBundle\Application\Port\PersonalDataEraser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;
use Tilttek\Badges\BadgeProjection;
use Tilttek\Badges\Badges;
use Tilttek\Badges\Catalog;
use Tilttek\Badges\Evaluator;
use Tilttek\Badges\Exception\InvalidBadge;
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
use Tilttek\BadgesBundle\Infrastructure\DependencyInjection\BadgesServices;
use Tilttek\BadgesBundle\Infrastructure\Doctrine\BadgesSchemaListener;
use Tilttek\BadgesBundle\Infrastructure\Doctrine\DbalAwardStore;
use Tilttek\BadgesBundle\Infrastructure\Doctrine\DbalGrantStore;
use Tilttek\BadgesBundle\Infrastructure\Doctrine\DbalProfileStore;
use Tilttek\BadgesBundle\Infrastructure\Doctrine\DbalProgressStore;
use Tilttek\BadgesBundle\Infrastructure\Event\DispatchingObserver;
use Tilttek\BadgesBundle\Infrastructure\OpenBadges\BadgeCredentials;
use Tilttek\BadgesBundle\Infrastructure\Privacy\BadgesPrivacy;
use Tilttek\BadgesBundle\Infrastructure\Twig\BadgesExtension;
use Tilttek\BadgesBundle\TilttekBadgesBundle;

#[CoversClass(TilttekBadgesBundle::class)]
#[CoversClass(BadgesServices::class)]
final class TilttekBadgesBundleTest extends TestCase
{
    public function testTheServices(): void
    {
        $container = $this->load([]);
        $connection = new Reference('doctrine.dbal.default_connection');

        self::assertSame('tilttek_badges', $this->extension()->getAlias());
        self::assertSame([
            'service_container', DbalProgressStore::class, DbalAwardStore::class, DbalProfileStore::class, DbalGrantStore::class, BadgesSchemaListener::class,
            Catalog::class, Evaluator::class, Badges::class, DispatchingObserver::class, BadgeProjection::class,
            AwardCommand::class, RevokeCommand::class, ListCommand::class, BadgesExtension::class, ...$this->privacy(),
        ], array_keys($container->getDefinitions()));

        foreach ([ProgressStore::class => DbalProgressStore::class, AwardStore::class => DbalAwardStore::class, ProfileStore::class => DbalProfileStore::class, GrantStore::class => DbalGrantStore::class] as $port => $store) {
            self::assertEquals([$connection], $container->getDefinition($store)->getArguments());
            self::assertFalse($container->getDefinition($store)->isPublic());
            self::assertSame([$store, true], [(string) $container->getAlias($port), $container->getAlias($port)->isPublic()]);
        }

        self::assertEquals([$connection], $container->getDefinition(BadgesSchemaListener::class)->getArguments());
        self::assertSame(['doctrine.event_listener' => [['event' => 'postGenerateSchema', 'connection' => 'default']]], $container->getDefinition(BadgesSchemaListener::class)->getTags());
        self::assertSame([Catalog::class, 'fromArray'], $container->getDefinition(Catalog::class)->getFactory());
        self::assertSame([[], 'UTC'], $container->getDefinition(Catalog::class)->getArguments());
        self::assertTrue($container->getDefinition(Catalog::class)->isPublic());
        self::assertEquals([new Reference(Catalog::class), new Reference(ProgressStore::class), new Reference(Measures::class, ContainerInterface::NULL_ON_INVALID_REFERENCE)], $container->getDefinition(Evaluator::class)->getArguments());
        self::assertEquals([new Reference(Evaluator::class), new Reference(ProgressStore::class), new Reference(AwardStore::class), new Reference(ProfileStore::class), new Reference(GrantStore::class), new Reference('clock'), 6], $container->getDefinition(Badges::class)->getArguments());
        self::assertTrue($container->getDefinition(Badges::class)->isPublic());
        self::assertEquals([new Reference(EventDispatcherInterface::class)], $container->getDefinition(DispatchingObserver::class)->getArguments());
        self::assertEquals([new Reference(Evaluator::class), new Reference(ProgressStore::class), new Reference(AwardStore::class), new Reference(DispatchingObserver::class), 'badges', 10], $container->getDefinition(BadgeProjection::class)->getArguments());
        self::assertSame(['tilttek_activity.projection' => [[]], 'kernel.reset' => [['method' => 'clearReplay']]], $container->getDefinition(BadgeProjection::class)->getTags());
        self::assertTrue($container->getDefinition(BadgeProjection::class)->isPublic());

        foreach ([AwardCommand::class, RevokeCommand::class, ListCommand::class] as $command) {
            self::assertEquals([new Reference(Badges::class)], $container->getDefinition($command)->getArguments());
            self::assertSame(['console.command' => [[]]], $container->getDefinition($command)->getTags());
        }

        self::assertEquals([new Reference(Badges::class), 'badges'], $container->getDefinition(BadgesExtension::class)->getArguments());
        self::assertSame(['twig.attribute_extension' => [[]], 'twig.runtime' => [[]]], $container->getDefinition(BadgesExtension::class)->getTags());
        self::assertTrue($container->getDefinition(BadgesExtension::class)->isPublic());
    }

    public function testThePrivacyModuleWhenPhilPrivacyBundleIsInstalled(): void
    {
        if (!interface_exists(PersonalDataEraser::class)) {
            self::markTestSkipped('phil/privacy-bundle is not installed: the export and erasure of a member are an optional integration.');
        }

        $container = $this->load([]);
        self::assertEquals([new Reference(Badges::class), 'member'], $container->getDefinition(BadgesPrivacy::class)->getArguments());
        self::assertSame(['phil_privacy.exporter' => [[]], 'phil_privacy.eraser' => [[]]], $container->getDefinition(BadgesPrivacy::class)->getTags());
        self::assertSame('user', $this->load(['privacy' => ['member_kind' => 'user']])->getDefinition(BadgesPrivacy::class)->getArgument(1));
    }

    public function testNoPrivacyModuleWithoutPhilPrivacyBundle(): void
    {
        if (interface_exists(PersonalDataEraser::class)) {
            self::markTestSkipped('phil/privacy-bundle is installed: its absence is checked where it is missing.');
        }

        self::assertFalse($this->load([])->hasDefinition(BadgesPrivacy::class));
    }

    public function testTheConfigurationReachesTheServices(): void
    {
        $badges = ['organiser' => ['name' => 'Organiser', 'criterion' => ['manual' => true]], 'streak' => ['criterion' => ['streak' => ['verbs' => ['site.visited'], 'days' => 7]]]];
        $container = $this->load([
            'store' => 'memory',
            'timezone' => 'America/Toronto',
            'badges' => $badges,
            'projection' => 'trophies',
            'showcase_size' => 3,
            'max_awards_per_activity' => 4,
            'translation_domain' => 'trophies',
        ]);

        self::assertSame([
            'service_container', InMemoryProgressStore::class, InMemoryAwardStore::class, InMemoryProfileStore::class, InMemoryGrantStore::class,
            Catalog::class, Evaluator::class, Badges::class, DispatchingObserver::class, BadgeProjection::class,
            AwardCommand::class, RevokeCommand::class, ListCommand::class, BadgesExtension::class, ...$this->privacy(),
        ], array_keys($container->getDefinitions()));
        self::assertSame([], $container->getDefinition(InMemoryAwardStore::class)->getArguments());
        self::assertSame(['kernel.reset' => [['method' => 'reset']]], $container->getDefinition(InMemoryProgressStore::class)->getTags());
        self::assertSame(['kernel.reset' => [['method' => 'reset']]], $container->getDefinition(InMemoryAwardStore::class)->getTags());
        self::assertSame(['kernel.reset' => [['method' => 'reset']]], $container->getDefinition(InMemoryProfileStore::class)->getTags());
        self::assertSame(['kernel.reset' => [['method' => 'reset']]], $container->getDefinition(InMemoryGrantStore::class)->getTags());
        self::assertSame([InMemoryGrantStore::class, true], [(string) $container->getAlias(GrantStore::class), $container->getAlias(GrantStore::class)->isPublic()]);
        self::assertSame([$badges, 'America/Toronto'], $container->getDefinition(Catalog::class)->getArguments());
        self::assertSame(3, $container->getDefinition(Badges::class)->getArgument(6));
        self::assertSame('trophies', $container->getDefinition(BadgeProjection::class)->getArgument(4));
        self::assertSame(4, $container->getDefinition(BadgeProjection::class)->getArgument(5));
        self::assertSame(BadgeProjection::MAX_AWARDS_PER_ACTIVITY, $this->load([])->getDefinition(BadgeProjection::class)->getArgument(5));
        self::assertSame('trophies', $container->getDefinition(BadgesExtension::class)->getArgument(1));
        self::assertSame(1, $this->load(['showcase_size' => 1])->getDefinition(Badges::class)->getArgument(6));
        self::assertSame(1, $this->load(['max_awards_per_activity' => 1])->getDefinition(BadgeProjection::class)->getArgument(5));
        self::assertTrue($this->load(['store' => 'doctrine'])->hasDefinition(DbalAwardStore::class));

        $other = $this->load(['connection' => 'gamification']);
        self::assertEquals([new Reference('doctrine.dbal.gamification_connection')], $other->getDefinition(DbalAwardStore::class)->getArguments());
        self::assertSame(['doctrine.event_listener' => [['event' => 'postGenerateSchema', 'connection' => 'gamification']]], $other->getDefinition(BadgesSchemaListener::class)->getTags());
    }

    public function testOpenBadgesOnTheServicesOfTheOpenBadgesBundle(): void
    {
        $container = $this->load(['open_badges' => ['achievement_url' => 'https://example.org/badges/{code}'], 'translation_domain' => 'trophies'], openBadgesBundle: true);

        self::assertEquals([new Reference(Badges::class), new Reference(CredentialIssuer::class), new Reference(Securing::class), 'https://example.org/badges/{code}', 'trophies', new Reference('translator', ContainerInterface::NULL_ON_INVALID_REFERENCE)], $container->getDefinition(BadgeCredentials::class)->getArguments());
        self::assertTrue($container->getDefinition(BadgeCredentials::class)->isPublic());
        self::assertFalse($container->hasDefinition(CredentialIssuer::class));
        self::assertFalse($this->load([], openBadgesBundle: true)->hasDefinition(BadgeCredentials::class));
    }

    public function testThePrependTellsWhetherTheOpenBadgesBundleIsRegistered(): void
    {
        if (!class_exists(PhilOpenBadgesBundle::class)) {
            self::markTestSkipped('phil/open-badges-bundle is not installed: the Open Badges credentials are an optional integration.');
        }

        $bundle = new TilttekBadgesBundle();
        $extension = $bundle->getContainerExtension();
        self::assertInstanceOf(PrependExtensionInterface::class, $extension);

        $without = new ContainerBuilder();
        $extension->prepend($without);
        self::assertFalse($without->getParameter(BadgesServices::OPEN_BADGES_PARAMETER));

        $with = new ContainerBuilder();
        $with->registerExtension(new PhilOpenBadgesBundle()->getContainerExtension() ?? throw new LogicException('No extension.'));

        $extension->prepend($with);
        self::assertTrue($with->getParameter(BadgesServices::OPEN_BADGES_PARAMETER));
    }

    public function testOpenBadgesNeedTheOpenBadgesBundle(): void
    {
        $this->expectExceptionObject(new InvalidConfigurationException('Open Badges credentials are issued by phil/open-badges-bundle: register Phil\\OpenBadgesBundle\\PhilOpenBadgesBundle and configure phil_open_badges.'));
        $this->load(['open_badges' => ['achievement_url' => 'https://example.org/badges/{code}']]);
    }

    public function testOpenBadgesNeedTheParameterOfThePrepend(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->extension()->load([['open_badges' => ['achievement_url' => 'https://example.org/badges/{code}']]], new ContainerBuilder());
    }

    public function testOpenBadgesNeedTheParameterToBeTrue(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $container = new ContainerBuilder();
        $container->setParameter(BadgesServices::OPEN_BADGES_PARAMETER, 1);
        $this->extension()->load([['open_badges' => ['achievement_url' => 'https://example.org/badges/{code}']]], $container);
    }

    public function testOpenBadgesNeedTheirAchievementUrl(): void
    {
        $this->expectExceptionObject(new InvalidConfigurationException('Open Badges credentials need the achievement URL: set tilttek_badges.open_badges.achievement_url.'));
        $this->load(['open_badges' => ['enabled' => true]], openBadgesBundle: true);
    }

    public function testAWrongBadgeFailsWhenTheContainerIsBuilt(): void
    {
        $this->expectException(InvalidBadge::class);
        $this->expectExceptionMessageIsOrContains('"Gold" is not a badge code');

        $this->load(['badges' => ['Gold' => ['criterion' => ['manual' => true]]]]);
    }

    public function testATimezoneThatIsNoneFailsWhenTheContainerIsBuilt(): void
    {
        $this->expectExceptionObject(InvalidBadge::because('"Mars/Olympus" is not a timezone.'));

        $this->load(['timezone' => 'Mars/Olympus']);
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function wrongConfigurations(): iterable
    {
        yield 'store' => [['store' => 'redis']];
        yield 'showcase' => [['showcase_size' => 0]];
        yield 'awards per activity' => [['max_awards_per_activity' => 0]];
        yield 'empty projection' => [['projection' => '']];
        yield 'empty domain' => [['translation_domain' => '']];
        yield 'issuer' => [['open_badges' => ['issuer' => ['id' => 'https://example.org/issuer']]]];
        yield 'empty member kind' => [['privacy' => ['member_kind' => '']]];
    }

    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('wrongConfigurations')]
    public function testAWrongConfigurationIsRefused(array $config): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->load($config);
    }

    /**
     * The privacy module, registered when phil/privacy-bundle is installed.
     *
     * @return list<class-string>
     */
    private function privacy(): array
    {
        return interface_exists(PersonalDataEraser::class) ? [BadgesPrivacy::class] : [];
    }

    /**
     * @param array<string, mixed> $config
     */
    private function load(array $config, bool $openBadgesBundle = false): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter(BadgesServices::OPEN_BADGES_PARAMETER, $openBadgesBundle);
        $this->extension()->load([$config], $container);

        return $container;
    }

    private function extension(): ExtensionInterface
    {
        $extension = new TilttekBadgesBundle()->getContainerExtension();
        self::assertInstanceOf(ExtensionInterface::class, $extension);

        return $extension;
    }
}
