<?php

declare(strict_types=1);

namespace Tilttek\LuckTokensBundle\Tests\Unit;

use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;
use Tilttek\LuckTokens\Contract\HolderAccess;
use Tilttek\LuckTokens\Contract\TokenLedger;
use Tilttek\LuckTokens\Contract\TokenRules;
use Tilttek\LuckTokens\Exception\InvalidTokenRule;
use Tilttek\LuckTokens\LuckTokens;
use Tilttek\LuckTokens\Store\InMemoryTokenLedger;
use Tilttek\LuckTokens\Store\SelfAccess;
use Tilttek\LuckTokens\TokenKind;
use Tilttek\LuckTokensBundle\Application\LockedLuckTokens;
use Tilttek\LuckTokensBundle\Application\LockScope;
use Tilttek\LuckTokensBundle\Infrastructure\DependencyInjection\LuckTokensServices;
use Tilttek\LuckTokensBundle\Infrastructure\Doctrine\DbalTokenLedger;
use Tilttek\LuckTokensBundle\Infrastructure\Doctrine\DbalTokenRules;
use Tilttek\LuckTokensBundle\Infrastructure\Doctrine\LuckTokensSchemaListener;
use Tilttek\LuckTokensBundle\Infrastructure\Privacy\LuckTokensPrivacy;
use Tilttek\LuckTokensBundle\Infrastructure\Rules\ConfiguredTokenRules;
use Tilttek\LuckTokensBundle\Infrastructure\Security\HolderControlVoter;
use Tilttek\LuckTokensBundle\Infrastructure\Security\SecurityHolderAccess;
use Tilttek\LuckTokensBundle\TilttekLuckTokensBundle;

#[CoversClass(TilttekLuckTokensBundle::class)]
#[CoversClass(LuckTokensServices::class)]
final class TilttekLuckTokensBundleTest extends TestCase
{
    public function testTheServicesByDefault(): void
    {
        $container = $this->load([]);
        $connection = new Reference('doctrine.dbal.default_connection');

        self::assertSame('tilttek_luck_tokens', $this->extension()->getAlias());
        self::assertSame([
            'service_container',
            DbalTokenLedger::class,
            ConfiguredTokenRules::class,
            LuckTokensSchemaListener::class,
            SelfAccess::class,
            LuckTokens::class,
            LuckTokensPrivacy::class,
        ], array_keys($container->getDefinitions()));

        foreach ([TokenLedger::class => DbalTokenLedger::class, TokenRules::class => ConfiguredTokenRules::class, HolderAccess::class => SelfAccess::class] as $port => $service) {
            self::assertSame([$service, true], [(string) $container->getAlias($port), $container->getAlias($port)->isPublic()]);
            self::assertFalse($container->getDefinition($service)->isPublic());
        }

        self::assertEquals([$connection], $container->getDefinition(DbalTokenLedger::class)->getArguments());
        self::assertSame([[]], $container->getDefinition(ConfiguredTokenRules::class)->getArguments());
        self::assertSame([], $container->getDefinition(SelfAccess::class)->getArguments());
        self::assertEquals([$connection, false], $container->getDefinition(LuckTokensSchemaListener::class)->getArguments());
        self::assertSame(['doctrine.event_listener' => [['event' => 'postGenerateSchema', 'connection' => 'default']]], $container->getDefinition(LuckTokensSchemaListener::class)->getTags());
        self::assertEquals([
            new Reference(TokenRules::class),
            new Reference(TokenLedger::class),
            new Reference(EventDispatcherInterface::class, ContainerInterface::IGNORE_ON_INVALID_REFERENCE),
            new Reference(HolderAccess::class),
            new Reference('clock'),
            100_000,
        ], $container->getDefinition(LuckTokens::class)->getArguments());
        self::assertSame(ContainerInterface::IGNORE_ON_INVALID_REFERENCE, $this->reference($container->getDefinition(LuckTokens::class)->getArgument(2))->getInvalidBehavior());
        self::assertTrue($container->getDefinition(LuckTokens::class)->isPublic());
        self::assertEquals([new Reference(LuckTokens::class), 'member', null, null], $container->getDefinition(LuckTokensPrivacy::class)->getArguments());
        self::assertSame(['phil_privacy.eraser' => [[]]], $container->getDefinition(LuckTokensPrivacy::class)->getTags());
    }

    public function testTheTablesOfTheConfiguration(): void
    {
        $tables = [
            'game-table:12' => [
                'game_masters' => ['member:7'],
                'kinds' => [
                    ['key' => 'inspiration', 'name' => 'Inspiration', 'cap' => 1, 'transferable' => true],
                    ['key' => 'bennies', 'name' => 'Bennies', 'cap' => 5, 'scope' => 'player', 'refresh' => 'each-session', 'refresh_to' => 3, 'carry_over' => 0, 'grants' => 'anyone'],
                ],
            ],
        ];
        $container = $this->load(['tables' => $tables, 'max_entries' => 1]);

        self::assertSame([$tables], $container->getDefinition(ConfiguredTokenRules::class)->getArguments(), "A table's reference is kept as it is written, dashes included.");
        self::assertSame(1, $container->getDefinition(LuckTokens::class)->getArgument(5));
        $rules = new ConfiguredTokenRules($tables)->rulesFor(ResourceReference::of('game-table', '12'));
        self::assertNotNull($rules);
        self::assertSame(['inspiration', 'bennies'], array_map(static fn (TokenKind $kind): string => $kind->key, $rules->kinds()));
    }

    public function testEveryOptionGiven(): void
    {
        $container = $this->load([
            'store' => 'doctrine',
            'connection' => 'tokens',
            'rules' => 'doctrine',
            'clock' => 'app.clock',
            'max_entries' => 5_000,
            'holder_access' => 'security',
            'lock' => ['enabled' => true, 'factory' => 'app.lock_factory', 'scope' => 'table', 'ttl' => 5],
            'privacy' => ['member_kind' => 'user'],
        ]);
        $connection = new Reference('doctrine.dbal.tokens_connection');

        self::assertSame([
            'service_container',
            DbalTokenLedger::class,
            DbalTokenRules::class,
            LuckTokensSchemaListener::class,
            HolderControlVoter::class,
            SecurityHolderAccess::class,
            LuckTokens::class,
            LockedLuckTokens::class,
            LuckTokensPrivacy::class,
        ], array_keys($container->getDefinitions()));
        self::assertEquals([$connection], $container->getDefinition(DbalTokenLedger::class)->getArguments());
        self::assertEquals([$connection], $container->getDefinition(DbalTokenRules::class)->getArguments());
        self::assertSame(DbalTokenRules::class, (string) $container->getAlias(TokenRules::class));
        self::assertEquals([$connection, true], $container->getDefinition(LuckTokensSchemaListener::class)->getArguments());
        self::assertSame(['doctrine.event_listener' => [['event' => 'postGenerateSchema', 'connection' => 'tokens']]], $container->getDefinition(LuckTokensSchemaListener::class)->getTags());
        self::assertSame(['security.voter' => [[]]], $container->getDefinition(HolderControlVoter::class)->getTags());
        self::assertEquals([new Reference('security.authorization_checker')], $container->getDefinition(SecurityHolderAccess::class)->getArguments());
        self::assertSame(SecurityHolderAccess::class, (string) $container->getAlias(HolderAccess::class));
        self::assertEquals(new Reference('app.clock'), $container->getDefinition(LuckTokens::class)->getArgument(4));
        self::assertSame(5_000, $container->getDefinition(LuckTokens::class)->getArgument(5));
        self::assertEquals([new Reference(LuckTokens::class), new Reference('app.lock_factory'), LockScope::Table, 5.0], $container->getDefinition(LockedLuckTokens::class)->getArguments());
        self::assertTrue($container->getDefinition(LockedLuckTokens::class)->isPublic());
        self::assertEquals([new Reference(LuckTokens::class), 'user', new Reference(LockedLuckTokens::class), new Reference(DbalTokenRules::class)], $container->getDefinition(LuckTokensPrivacy::class)->getArguments());
    }

    public function testTheServicesOfTheApplicationAndTheMemoryStore(): void
    {
        $container = $this->load([
            'store' => 'memory',
            'rules' => 'service',
            'rules_service' => 'app.rules',
            'holder_access' => 'service',
            'holder_access_service' => 'app.access',
            'lock' => ['enabled' => true],
        ]);

        self::assertSame([
            'service_container',
            InMemoryTokenLedger::class,
            LuckTokens::class,
            LockedLuckTokens::class,
            LuckTokensPrivacy::class,
        ], array_keys($container->getDefinitions()));
        self::assertSame([], $container->getDefinition(InMemoryTokenLedger::class)->getArguments());
        self::assertSame(InMemoryTokenLedger::class, (string) $container->getAlias(TokenLedger::class));
        self::assertSame('app.rules', (string) $container->getAlias(TokenRules::class));
        self::assertTrue($container->getAlias(TokenRules::class)->isPublic());
        self::assertSame('app.access', (string) $container->getAlias(HolderAccess::class));
        self::assertTrue($container->getAlias(HolderAccess::class)->isPublic());
        self::assertEquals([new Reference(LuckTokens::class), new Reference('lock.factory'), LockScope::Holder, 30.0], $container->getDefinition(LockedLuckTokens::class)->getArguments());
    }

    public function testEveryValueOfTheChoicesIsAccepted(): void
    {
        foreach (['configuration', 'doctrine'] as $rules) {
            $container = $this->load(['rules' => $rules, 'tables' => 'configuration' === $rules ? [] : []]);
            self::assertTrue($container->hasAlias(TokenRules::class));
        }

        $container = $this->load(['holder_access' => 'self']);
        self::assertSame(SelfAccess::class, (string) $container->getAlias(HolderAccess::class));

        $container = $this->load(['tables' => ['table:1' => ['kinds' => [
            ['key' => 'a', 'name' => 'A', 'cap' => 1, 'scope' => 'character', 'refresh' => 'never', 'grants' => 'game-masters'],
            ['key' => 'b', 'name' => 'B', 'cap' => 3, 'scope' => 'player', 'refresh' => 'each-session', 'refresh_to' => 1, 'grants' => 'anyone'],
        ]]]]);
        self::assertTrue($container->hasAlias(TokenRules::class));
    }

    public function testAServiceIdWrittenAsANumberIsAStringAllTheSame(): void
    {
        $container = $this->load(['rules' => 'service', 'rules_service' => 7, 'holder_access' => 'service', 'holder_access_service' => 8]);

        self::assertSame(['7', '8'], [(string) $container->getAlias(TokenRules::class), (string) $container->getAlias(HolderAccess::class)]);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function wrongConfigurations(): iterable
    {
        yield 'tables with rules elsewhere' => [['rules' => 'doctrine', 'tables' => ['table:1' => ['kinds' => []]]], 'The rules come from "doctrine": leave "tables" empty, or set "rules: configuration".'];
        yield 'rules in the database of a memory store' => [['store' => 'memory', 'rules' => 'doctrine'], 'Rules kept in the database need the "doctrine" store.'];
        yield 'rules of no service' => [['rules' => 'service'], '"rules: service" needs "rules_service", the id of the service.'];
        yield 'access of no service' => [['holder_access' => 'service'], '"holder_access: service" needs "holder_access_service", the id of the service.'];
        yield 'store' => [['store' => 'files'], 'The value "files" is not allowed for path "tilttek_luck_tokens.store"'];
        yield 'connection' => [['connection' => ''], 'The path "tilttek_luck_tokens.connection" cannot contain an empty value'];
        yield 'clock' => [['clock' => ''], 'The path "tilttek_luck_tokens.clock" cannot contain an empty value'];
        yield 'max entries' => [['max_entries' => 0], 'The value 0 is too small for path "tilttek_luck_tokens.max_entries"'];
        yield 'lock scope' => [['lock' => ['scope' => 'room']], 'The value "room" is not allowed for path "tilttek_luck_tokens.lock.scope"'];
        yield 'lock ttl' => [['lock' => ['ttl' => 0.5]], 'The value 0.5 is too small for path "tilttek_luck_tokens.lock.ttl"'];
        yield 'lock factory' => [['lock' => ['factory' => '']], 'The path "tilttek_luck_tokens.lock.factory" cannot contain an empty value'];
        yield 'member kind' => [['privacy' => ['member_kind' => '']], 'The path "tilttek_luck_tokens.privacy.member_kind" cannot contain an empty value'];
        yield 'kind without a cap' => [['tables' => ['table:1' => ['kinds' => [['key' => 'luck', 'name' => 'Luck']]]]], 'The child config "cap" under "tilttek_luck_tokens.tables.table:1.kinds.0" must be configured'];
        yield 'kind scope' => [['tables' => ['table:1' => ['kinds' => [['key' => 'luck', 'name' => 'Luck', 'cap' => 1, 'scope' => 'party']]]]], 'The value "party" is not allowed'];
        yield 'kind refresh' => [['tables' => ['table:1' => ['kinds' => [['key' => 'luck', 'name' => 'Luck', 'cap' => 1, 'refresh' => 'daily']]]]], 'The value "daily" is not allowed'];
        yield 'kind grants' => [['tables' => ['table:1' => ['kinds' => [['key' => 'luck', 'name' => 'Luck', 'cap' => 1, 'grants' => 'players']]]]], 'The value "players" is not allowed'];
    }

    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('wrongConfigurations')]
    public function testAWrongConfigurationIsRefused(array $config, string $message): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains($message);

        $this->load($config);
    }

    public function testRulesTheLibraryRefusesAreRefusedWhenTheContainerIsCompiled(): void
    {
        $this->expectException(InvalidTokenRule::class);

        $this->load(['tables' => ['table:1' => ['kinds' => [['key' => 'luck', 'name' => 'Luck', 'cap' => 1, 'refresh_to' => 1]]]]]);
    }

    private function reference(mixed $argument): Reference
    {
        self::assertInstanceOf(Reference::class, $argument);

        return $argument;
    }

    /**
     * @param array<string, mixed> $config
     */
    private function load(array $config): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $this->extension()->load([$config], $container);

        return $container;
    }

    private function extension(): ExtensionInterface
    {
        $extension = new TilttekLuckTokensBundle()->getContainerExtension();
        self::assertInstanceOf(ExtensionInterface::class, $extension);

        return $extension;
    }
}
