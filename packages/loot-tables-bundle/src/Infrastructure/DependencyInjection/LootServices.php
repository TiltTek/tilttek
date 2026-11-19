<?php

declare(strict_types=1);

namespace Tilttek\LootTablesBundle\Infrastructure\DependencyInjection;

use Phil\CommerceBundle\Application\LineOptions;
use Phil\CommerceBundle\Domain\Repository\OrderRepository;
use Phil\CommerceBundle\PhilCommerceBundle;
use Phil\PagedMedia\Pdf\Document;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Form\AbstractType;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;
use Tilttek\LootTables\Draw\LootDrawer;
use Tilttek\LootTables\LootCatalog;
use Tilttek\LootTables\Reading\CatalogReader;
use Tilttek\LootTablesBundle\Application\Drawers;
use Tilttek\LootTablesBundle\Application\LootCommitments;
use Tilttek\LootTablesBundle\Application\LootOptionDrawer;
use Tilttek\LootTablesBundle\Application\LootPrint;
use Tilttek\LootTablesBundle\Application\LootResults;
use Tilttek\LootTablesBundle\Application\PhysicalDice;
use Tilttek\LootTablesBundle\Application\SecureSeeds;
use Tilttek\LootTablesBundle\Domain\Repository\LootDrawRepository;
use Tilttek\LootTablesBundle\Infrastructure\Console\ReplayDrawCommand;
use Tilttek\LootTablesBundle\Infrastructure\Doctrine\DoctrineLootDrawRepository;
use Tilttek\LootTablesBundle\Infrastructure\Form\PhysicalDiceType;
use Tilttek\LootTablesBundle\Infrastructure\Memory\InMemoryLootDrawRepository;
use Tilttek\LootTablesBundle\Infrastructure\Pdf\LootSheetPdf;
use Tilttek\LootTablesBundle\Infrastructure\Twig\Components\CommitmentsComponent;
use Tilttek\LootTablesBundle\Infrastructure\Twig\Components\PhysicalDiceComponent;
use Tilttek\LootTablesBundle\Infrastructure\Twig\KitComponents;
use Tilttek\LootTablesBundle\Infrastructure\Workflow\CommitOnPlace;

/**
 * The bundle's services: the catalogue of the configuration, read once here so that a wrong table or box fails when
 * the container is compiled; the drawer of the library; the cryptographic source of the seeds; the store of the draws;
 * the commitments and the listener that makes them when an order is placed, the option drawer the commerce core calls
 * once an order is paid, the entry of physical dice, the results and what is printed of them; and, when their packages
 * are installed, the form of the physical dice, the components of the register and of the buyer's page, the command
 * that rolls a draw again, and the production sheet.
 */
final class LootServices
{
    private function __construct()
    {
    }

    /**
     * @param array{tables: array<string, mixed>, boxes: array<string, mixed>, require_commitment: bool, commit_on_place: bool, store: string, entity_manager: string, kit_subdirectory: ?string} $config
     */
    public static function register(ContainerBuilder $container, array $config): void
    {
        $catalog = ['tables' => $config['tables'], 'boxes' => $config['boxes']];
        self::catalog($catalog);

        $container->register(LootCatalog::class, LootCatalog::class)->setFactory([self::class, 'catalog'])->setArguments([$catalog])->setPublic(true);
        $container->register(LootDrawer::class, LootDrawer::class);
        $container->register(SecureSeeds::class, SecureSeeds::class);
        $container->register(Drawers::class, Drawers::class)->setArguments([new Reference(LootCatalog::class)]);

        if ('memory' === $config['store']) {
            $container->register(InMemoryLootDrawRepository::class, InMemoryLootDrawRepository::class)->addTag('kernel.reset', ['method' => 'reset']);
            $container->setAlias(LootDrawRepository::class, InMemoryLootDrawRepository::class)->setPublic(true);
        } else {
            $container->register(DoctrineLootDrawRepository::class, DoctrineLootDrawRepository::class)
                ->setArguments([new Reference(\sprintf('doctrine.orm.%s_entity_manager', $config['entity_manager']))]);
            $container->setAlias(LootDrawRepository::class, DoctrineLootDrawRepository::class)->setPublic(true);
        }

        $container->register(LootCommitments::class, LootCommitments::class)
            ->setArguments([new Reference(Drawers::class), new Reference(LineOptions::class), new Reference(LootDrawRepository::class), new Reference(SecureSeeds::class), new Reference('clock')])
            ->setPublic(true);
        $container->register(LootOptionDrawer::class, LootOptionDrawer::class)
            ->setArguments([new Reference(Drawers::class), new Reference(LootCommitments::class), new Reference(LootDrawRepository::class), new Reference(LootDrawer::class), new Reference('clock'), $config['require_commitment']])
            ->addTag(PhilCommerceBundle::OPTION_DRAWER_TAG);
        $container->register(PhysicalDice::class, PhysicalDice::class)
            ->setArguments([new Reference(Drawers::class), new Reference(LootDrawRepository::class), new Reference('clock')])
            ->setPublic(true);
        $container->register(LootResults::class, LootResults::class)
            ->setArguments([new Reference(Drawers::class), new Reference(LootDrawRepository::class), new Reference(LootDrawer::class)])
            ->setPublic(true);
        $container->register(LootPrint::class, LootPrint::class)
            ->setArguments([new Reference(LootResults::class)])
            ->setPublic(true);

        if ($config['commit_on_place']) {
            $container->register(CommitOnPlace::class, CommitOnPlace::class)
                ->setArguments([new Reference(LootCommitments::class)])
                ->addTag('kernel.event_listener', ['event' => CommitOnPlace::EVENT]);
        }

        if (class_exists(AbstractType::class)) {
            $container->register(PhysicalDiceType::class, PhysicalDiceType::class)
                ->setArguments([new Reference('translator', ContainerInterface::NULL_ON_INVALID_REFERENCE)])
                ->addTag('form.type');
        }

        if (class_exists(AsTwigComponent::class)) {
            $container->register(PhysicalDiceComponent::class, PhysicalDiceComponent::class)->setAutoconfigured(true);
            $container->register(KitComponents::class, KitComponents::class)->setArguments([$config['kit_subdirectory']]);
            $container->register(CommitmentsComponent::class, CommitmentsComponent::class)
                ->setArguments([new Reference(OrderRepository::class), new Reference(LootResults::class), new Reference(KitComponents::class)])
                ->setAutoconfigured(true);
        }

        if (class_exists(Command::class)) {
            $container->register(ReplayDrawCommand::class, ReplayDrawCommand::class)
                ->setArguments([new Reference(LootResults::class)])
                ->addTag('console.command', ['command' => ReplayDrawCommand::NAME, 'description' => 'Rolls a revealed loot draw again from its seed and checks it against its commitment']);
        }

        if (class_exists(Document::class)) {
            $container->register(LootSheetPdf::class, LootSheetPdf::class)
                ->setArguments([new Reference(LootPrint::class)])
                ->setPublic(true);
        }
    }

    /**
     * The catalogue of `tilttek_loot_tables.tables` and `tilttek_loot_tables.boxes`.
     *
     * @param array<array-key, mixed> $catalog
     */
    public static function catalog(array $catalog): LootCatalog
    {
        return new CatalogReader()->read($catalog);
    }
}
