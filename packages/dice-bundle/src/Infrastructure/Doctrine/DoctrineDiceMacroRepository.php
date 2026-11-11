<?php

declare(strict_types=1);

namespace Tilttek\DiceBundle\Infrastructure\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Override;
use Phil\Resource\ResourceReference;
use Tilttek\DiceBundle\Domain\MacroLevel;
use Tilttek\DiceBundle\Domain\Model\DiceMacro;
use Tilttek\DiceBundle\Domain\Repository\DiceMacroRepository;

/**
 * The macros in the table `tilttek_dice_macro`, through the entity manager; each save, removal and erasure is flushed.
 */
final readonly class DoctrineDiceMacroRepository implements DiceMacroRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function get(MacroLevel $level, ResourceReference $owner, string $name): ?DiceMacro
    {
        return $this->entityManager->find(DiceMacro::class, ['level' => $level->value, 'owner' => $owner->toString(), 'name' => $name]);
    }

    #[Override]
    public function save(DiceMacro $macro): void
    {
        $this->entityManager->persist($macro);
        $this->entityManager->flush();
    }

    #[Override]
    public function remove(DiceMacro $macro): void
    {
        $this->entityManager->remove($macro);
        $this->entityManager->flush();
    }

    #[Override]
    public function ofOwner(MacroLevel $level, ResourceReference $owner): array
    {
        return $this->entityManager->getRepository(DiceMacro::class)->findBy(['level' => $level->value, 'owner' => $owner->toString()], ['name' => 'ASC']);
    }

    #[Override]
    public function eraseOwner(ResourceReference $owner): int
    {
        $macros = $this->entityManager->getRepository(DiceMacro::class)->findBy(['owner' => $owner->toString()]);

        foreach ($macros as $macro) {
            $this->entityManager->remove($macro);
        }

        $this->entityManager->flush();

        return \count($macros);
    }
}
