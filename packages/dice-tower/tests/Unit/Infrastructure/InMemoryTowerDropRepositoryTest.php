<?php

declare(strict_types=1);

namespace Tilttek\DiceTower\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use Tilttek\DiceTower\Domain\Contract\TowerDropRepository;
use Tilttek\DiceTower\Domain\Model\TowerDrop;
use Tilttek\DiceTower\Infrastructure\Memory\InMemoryTowerDropRepository;
use Tilttek\DiceTower\Tests\Unit\Fixture\Tower;

#[CoversClass(InMemoryTowerDropRepository::class)]
#[CoversClass(TowerDrop::class)]
final class InMemoryTowerDropRepositoryTest extends TowerDropRepositoryTestCase
{
    public function testTheLogIsEmptiedBetweenTwoRequests(): void
    {
        $repository = new InMemoryTowerDropRepository();
        $repository->append(Tower::table(), 'aaaa', null, new DateTimeImmutable());
        $repository->append(Tower::table(), 'bbbb', null, new DateTimeImmutable());
        $repository->reset();

        self::assertSame([], $repository->latest(Tower::table(), 10));
        self::assertSame(1, $repository->append(Tower::table(), 'cccc', null, new DateTimeImmutable())->position());
    }

    #[Override]
    protected function repository(): TowerDropRepository
    {
        return new InMemoryTowerDropRepository();
    }
}
