<?php

declare(strict_types=1);

namespace Tilttek\DiceBundle\Tests\Unit\Infrastructure;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use ReflectionObject;
use Tilttek\DiceBundle\Domain\Repository\RecordedRollRepository;
use Tilttek\DiceBundle\Infrastructure\Memory\InMemoryRecordedRollRepository;

#[CoversClass(InMemoryRecordedRollRepository::class)]
final class InMemoryRepositoryTest extends RepositoryTestCase
{
    #[Override]
    protected function repository(): RecordedRollRepository
    {
        return new InMemoryRecordedRollRepository();
    }

    public function testResetLeavesTheRepositoryAsNew(): void
    {
        $repository = new InMemoryRecordedRollRepository();

        foreach (new ReflectionObject($repository)->getProperties() as $property) {
            $property->setValue($repository, ['kept']);
        }

        $repository->reset();

        self::assertEquals(new InMemoryRecordedRollRepository(), $repository);
    }
}
