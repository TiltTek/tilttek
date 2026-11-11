<?php

declare(strict_types=1);

namespace Tilttek\DiceBundle\Infrastructure\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Override;
use Phil\Resource\ResourceReference;
use Tilttek\DiceBundle\Domain\Model\RecordedRoll;
use Tilttek\DiceBundle\Domain\Repository\RecordedRollRepository;

/**
 * The journal in the table `tilttek_dice_roll`, through the entity manager; each save and erasure is flushed.
 */
final readonly class DoctrineRecordedRollRepository implements RecordedRollRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function get(string $id): ?RecordedRoll
    {
        return $this->entityManager->find(RecordedRoll::class, $id);
    }

    #[Override]
    public function save(RecordedRoll $roll): void
    {
        $this->entityManager->persist($roll);
        $this->entityManager->flush();
    }

    #[Override]
    public function ofOwner(ResourceReference $owner, int $limit): array
    {
        return $this->entityManager->getRepository(RecordedRoll::class)->findBy(['owner' => $owner->toString()], ['rolledAt' => 'DESC', 'id' => 'ASC'], $limit);
    }

    #[Override]
    public function eraseOwner(ResourceReference $owner): int
    {
        $rolls = $this->entityManager->getRepository(RecordedRoll::class)->findBy(['owner' => $owner->toString()]);

        foreach ($rolls as $roll) {
            $this->entityManager->remove($roll);
        }

        $this->entityManager->flush();

        return \count($rolls);
    }
}
