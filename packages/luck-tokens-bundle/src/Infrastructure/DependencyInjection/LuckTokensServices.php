<?php

declare(strict_types=1);

namespace Tilttek\LuckTokensBundle\Infrastructure\DependencyInjection;

use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Tilttek\LuckTokens\Contract\HolderAccess;
use Tilttek\LuckTokens\Contract\TokenLedger;
use Tilttek\LuckTokens\Contract\TokenRules;
use Tilttek\LuckTokens\LuckTokens;
use Tilttek\LuckTokens\Store\InMemoryTokenLedger;
use Tilttek\LuckTokens\Store\SelfAccess;
use Tilttek\LuckTokensBundle\Application\LockedLuckTokens;
use Tilttek\LuckTokensBundle\Application\LockScope;
use Tilttek\LuckTokensBundle\Infrastructure\Doctrine\DbalTokenLedger;
use Tilttek\LuckTokensBundle\Infrastructure\Doctrine\DbalTokenRules;
use Tilttek\LuckTokensBundle\Infrastructure\Doctrine\LuckTokensSchemaListener;
use Tilttek\LuckTokensBundle\Infrastructure\Rules\ConfiguredTokenRules;
use Tilttek\LuckTokensBundle\Infrastructure\Security\HolderControlVoter;
use Tilttek\LuckTokensBundle\Infrastructure\Security\SecurityHolderAccess;

/**
 * The bundle's services: the ledger on Doctrine DBAL or in memory, the rules from the configuration, the database or a
 * service, the access of actors to holders, `LuckTokens` with the application's event dispatcher and clock, and —
 * when the configuration asks — `LockedLuckTokens`; the schema listener when the ORM is installed. The configuration
 * is checked when the container is compiled.
 *
 * @phpstan-type Kind array{key: string, name: string, cap: int, scope?: string, refresh?: string, refresh_to?: int, carry_over?: int, grants?: string, transferable?: bool}
 * @phpstan-type Config array{store: string, connection: string, rules: string, rules_service: ?string, tables: array<string, array{game_masters: list<string>, kinds: list<Kind>}>, clock: string, max_entries: int, holder_access: string, holder_access_service: ?string, lock: array{enabled: bool, factory: string, scope: string, ttl: float}, privacy: array{member_kind: string}}
 */
final class LuckTokensServices
{
    private function __construct()
    {
    }

    /**
     * @param Config $config
     */
    public static function register(ContainerBuilder $container, array $config): void
    {
        self::check($config);
        $doctrine = 'doctrine' === $config['store'];
        $connection = new Reference(\sprintf('doctrine.dbal.%s_connection', $config['connection']));

        $container->setAlias(TokenLedger::class, $doctrine
            ? self::service($container, DbalTokenLedger::class, [$connection])
            : self::service($container, InMemoryTokenLedger::class))->setPublic(true);
        $container->setAlias(TokenRules::class, match ($config['rules']) {
            'doctrine' => self::service($container, DbalTokenRules::class, [$connection]),
            'configuration' => self::service($container, ConfiguredTokenRules::class, [self::tables($config)]),
            default => (string) $config['rules_service'],
        })->setPublic(true);

        if ($doctrine && class_exists(GenerateSchemaEventArgs::class)) {
            $container->register(LuckTokensSchemaListener::class, LuckTokensSchemaListener::class)
                ->setArguments([$connection, 'doctrine' === $config['rules']])
                ->addTag('doctrine.event_listener', ['event' => 'postGenerateSchema', 'connection' => $config['connection']]);
        }

        $container->setAlias(HolderAccess::class, match ($config['holder_access']) {
            'security' => self::security($container),
            'self' => self::service($container, SelfAccess::class),
            default => (string) $config['holder_access_service'],
        })->setPublic(true);

        $container->register(LuckTokens::class, LuckTokens::class)
            ->setArguments([
                new Reference(TokenRules::class),
                new Reference(TokenLedger::class),
                new Reference(EventDispatcherInterface::class, ContainerInterface::IGNORE_ON_INVALID_REFERENCE),
                new Reference(HolderAccess::class),
                new Reference($config['clock']),
                $config['max_entries'],
            ])
            ->setPublic(true);

        if ($config['lock']['enabled']) {
            $container->register(LockedLuckTokens::class, LockedLuckTokens::class)
                ->setArguments([
                    new Reference(LuckTokens::class),
                    new Reference($config['lock']['factory']),
                    LockScope::from($config['lock']['scope']),
                    $config['lock']['ttl'],
                ])
                ->setPublic(true);
        }
    }

    /**
     * The arguments of the erasure module the bundle class registers when `phil/privacy-bundle` is installed: the
     * tokens, the kind of a member's reference, and the locked tokens when the locks are enabled.
     *
     * @param Config $config
     *
     * @return list<Reference|string|null>
     */
    public static function privacyArguments(array $config): array
    {
        return [
            new Reference(LuckTokens::class),
            $config['privacy']['member_kind'],
            $config['lock']['enabled'] ? new Reference(LockedLuckTokens::class) : null,
        ];
    }

    /**
     * The tables of the configuration as `TableRules::fromArray()` reads them, checked now rather than at the first
     * operation.
     *
     * @param Config $config
     *
     * @return array<string, array{game_masters: list<string>, kinds: list<Kind>}>
     */
    private static function tables(array $config): array
    {
        new ConfiguredTokenRules($config['tables']);

        return $config['tables'];
    }

    /**
     * Registers a service under its class, and gives its id.
     *
     * @param class-string $class
     * @param list<mixed>  $arguments
     */
    private static function service(ContainerBuilder $container, string $class, array $arguments = []): string
    {
        $container->register($class, $class)->setArguments($arguments);

        return $class;
    }

    /**
     * The access asked of the security voters, with the voter that grants each holder their own tokens.
     */
    private static function security(ContainerBuilder $container): string
    {
        $container->register(HolderControlVoter::class, HolderControlVoter::class)->addTag('security.voter');

        return self::service($container, SecurityHolderAccess::class, [new Reference('security.authorization_checker')]);
    }

    /**
     * Refuses at compile time what would fail at the first operation.
     *
     * @param Config $config
     */
    private static function check(array $config): void
    {
        if ('configuration' !== $config['rules'] && [] !== $config['tables']) {
            throw new InvalidConfigurationException(\sprintf('The rules come from "%s": leave "tables" empty, or set "rules: configuration".', $config['rules']));
        }

        if ('doctrine' === $config['rules'] && 'doctrine' !== $config['store']) {
            throw new InvalidConfigurationException('Rules kept in the database need the "doctrine" store.');
        }

        foreach (['rules', 'holder_access'] as $option) {
            if ('service' === $config[$option] && null === $config[$option.'_service']) {
                throw new InvalidConfigurationException(\sprintf('"%s: service" needs "%s_service", the id of the service.', $option, $option));
            }
        }

        if ('security' === $config['holder_access'] && !interface_exists(AuthorizationCheckerInterface::class)) {
            throw new InvalidConfigurationException('"holder_access: security" needs symfony/security-core; run "composer require symfony/security-bundle".');
        }

        if ($config['lock']['enabled'] && !class_exists(LockFactory::class)) {
            throw new InvalidConfigurationException('The locks need symfony/lock; run "composer require symfony/lock".');
        }
    }
}
