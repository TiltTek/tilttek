<?php

declare(strict_types=1);

namespace Tilttek\DiceBundle\Tests\Unit\Infrastructure;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use ReflectionObject;
use Tilttek\DiceBundle\Domain\Model\DiceMacro;
use Tilttek\DiceBundle\Domain\Repository\DiceMacroRepository;
use Tilttek\DiceBundle\Infrastructure\Memory\InMemoryDiceMacroRepository;

#[CoversClass(InMemoryDiceMacroRepository::class)]
#[CoversClass(DiceMacro::class)]
final class InMemoryMacroRepositoryTest extends MacroRepositoryTestCase
{
    #[Override]
    protected function repository(): DiceMacroRepository
    {
        return new InMemoryDiceMacroRepository();
    }

    public function testResetLeavesTheRepositoryAsNew(): void
    {
        $repository = new InMemoryDiceMacroRepository();

        foreach (new ReflectionObject($repository)->getProperties() as $property) {
            $property->setValue($repository, ['kept']);
        }

        $repository->reset();

        self::assertEquals(new InMemoryDiceMacroRepository(), $repository);
    }
}
