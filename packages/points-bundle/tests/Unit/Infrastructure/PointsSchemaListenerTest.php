<?php

declare(strict_types=1);

namespace Tilttek\PointsBundle\Tests\Unit\Infrastructure;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\PointsBundle\Infrastructure\Doctrine\PointsSchemaListener;
use Tilttek\PointsBundle\Infrastructure\Doctrine\PointsTables;

#[CoversClass(PointsSchemaListener::class)]
final class PointsSchemaListenerTest extends TestCase
{
    public function testTheTablesJoinTheSchemaOfTheLedgersConnectionOnly(): void
    {
        $ledger = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $other = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $listener = new PointsSchemaListener($ledger);

        $event = new GenerateSchemaEventArgs($this->entityManager($ledger), new Schema());
        $listener->postGenerateSchema($event);
        self::assertTrue($event->getSchema()->hasTable(PointsTables::MOVEMENTS));
        self::assertTrue($event->getSchema()->hasTable(PointsTables::LOCKS));

        $elsewhere = new GenerateSchemaEventArgs($this->entityManager($other), new Schema());
        $listener->postGenerateSchema($elsewhere);
        self::assertSame([], $elsewhere->getSchema()->getTables());
    }

    private function entityManager(Connection $connection): EntityManager
    {
        $configuration = ORMSetup::createAttributeMetadataConfig([], true);
        $configuration->enableNativeLazyObjects(true);

        return new EntityManager($connection, $configuration);
    }
}
