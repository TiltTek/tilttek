<?php

declare(strict_types=1);

namespace Tilttek\LootTablesBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use Tilttek\LootTables\Draw\DrawSource;
use Tilttek\LootTables\Draw\LootResult;
use Tilttek\LootTablesBundle\Domain\Model\LootDraw;
use Tilttek\LootTablesBundle\Domain\Repository\LootDrawRepository;
use Tilttek\LootTablesBundle\Infrastructure\Memory\InMemoryLootDrawRepository;
use Tilttek\LootTablesBundle\Tests\Unit\Fixture\Shop;

#[CoversClass(InMemoryLootDrawRepository::class)]
#[CoversClass(LootDraw::class)]
final class InMemoryRepositoryTest extends RepositoryTestCase
{
    public function testTheResetEmptiesIt(): void
    {
        $repository = new InMemoryLootDrawRepository();
        $draw = LootDraw::physical('1042', 'product:a', 'loot', 1, 'loot-box:starter', LootResult::ofFaces(Shop::catalog()->box('starter'), [1, 1], DrawSource::Physical), 'x', new DateTimeImmutable());
        $repository->save($draw);
        $repository->reset();

        self::assertNull($repository->get($draw->id()));
    }

    #[Override]
    protected function repository(): LootDrawRepository
    {
        return new InMemoryLootDrawRepository();
    }

    #[Override]
    protected function clear(): void
    {
    }
}
