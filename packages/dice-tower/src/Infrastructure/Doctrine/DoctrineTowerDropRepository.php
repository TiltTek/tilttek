<?php

declare(strict_types=1);

namespace Tilttek\DiceTower\Infrastructure\Doctrine;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Phil\Resource\ResourceReference;
use Tilttek\DiceTower\Domain\Contract\TowerDropRepository;
use Tilttek\DiceTower\Domain\Model\TowerDrop;

/**
 * The log of the towers in the table `tilttek_dice_tower_drop`, through the entity manager: the database gives each
 * drop its position as it inserts it, so that two processes dropping at once never share one. Each append and erasure
 * is flushed.
 */
final readonly class DoctrineTowerDropRepository implements TowerDropRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function append(ResourceReference $table, string $roll, ?ResourceReference $roller, DateTimeImmutable $at): TowerDrop
    {
        $drop = TowerDrop::drop($table, $roll, $roller, $at);
        $this->entityManager->persist($drop);
        $this->entityManager->flush();

        return $drop;
    }

    #[Override]
    public function after(ResourceReference $table, int $position, int $limit): array
    {
        /** @var list<TowerDrop> */
        return $this->entityManager->createQueryBuilder()
            ->select('drop')
            ->from(TowerDrop::class, 'drop')
            ->where('drop.table = :table')
            ->andWhere('drop.position > :position')
            ->orderBy('drop.position', 'ASC')
            ->setParameter('table', $table->toString())
            ->setParameter('position', $position)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    #[Override]
    public function latest(ResourceReference $table, int $limit): array
    {
        /** @var list<TowerDrop> $drops */
        $drops = $this->entityManager->getRepository(TowerDrop::class)->findBy(['table' => $table->toString()], ['position' => 'DESC'], $limit);

        return array_reverse($drops);
    }

    #[Override]
    public function at(ResourceReference $table, int $position): ?TowerDrop
    {
        return $this->entityManager->getRepository(TowerDrop::class)->findOneBy(['table' => $table->toString(), 'position' => $position]);
    }

    #[Override]
    public function eraseRoller(ResourceReference $roller): int
    {
        $drops = $this->entityManager->getRepository(TowerDrop::class)->findBy(['roller' => $roller->toString()]);

        foreach ($drops as $drop) {
            $this->entityManager->remove($drop);
        }

        $this->entityManager->flush();

        return \count($drops);
    }
}
