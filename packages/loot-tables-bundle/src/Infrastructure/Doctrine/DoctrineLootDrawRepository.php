<?php

declare(strict_types=1);

namespace Tilttek\LootTablesBundle\Infrastructure\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Override;
use Tilttek\LootTablesBundle\Domain\Model\LootDraw;
use Tilttek\LootTablesBundle\Domain\Repository\LootDrawRepository;

/**
 * The draws in the table `tilttek_loot_draw`, through the entity manager; each save is flushed.
 */
final readonly class DoctrineLootDrawRepository implements LootDrawRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function get(string $id): ?LootDraw
    {
        return $this->entityManager->find(LootDraw::class, $id);
    }

    #[Override]
    public function save(LootDraw $draw): void
    {
        $this->entityManager->persist($draw);
        $this->entityManager->flush();
    }

    #[Override]
    public function ofOrder(string $orderNumber): array
    {
        return $this->entityManager->getRepository(LootDraw::class)->findBy(['orderNumber' => $orderNumber], ['line' => 'ASC', 'option' => 'ASC', 'unit' => 'ASC']);
    }
}
