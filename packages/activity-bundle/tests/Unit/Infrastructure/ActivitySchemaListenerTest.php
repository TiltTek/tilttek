<?php

declare(strict_types=1);

namespace Tilttek\ActivityBundle\Tests\Unit\Infrastructure;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\ActivityBundle\Infrastructure\Doctrine\ActivitySchemaListener;
use Tilttek\ActivityBundle\Infrastructure\Doctrine\ActivityTables;

#[CoversClass(ActivitySchemaListener::class)]
final class ActivitySchemaListenerTest extends TestCase
{
    public function testTheTablesJoinTheSchemaOfTheJournalsConnectionOnly(): void
    {
        $journal = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $other = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $listener = new ActivitySchemaListener($journal);

        $event = new GenerateSchemaEventArgs($this->entityManager($journal), new Schema());
        $listener->postGenerateSchema($event);
        self::assertTrue($event->getSchema()->hasTable(ActivityTables::ENTRIES));
        self::assertTrue($event->getSchema()->hasTable(ActivityTables::CHECKPOINTS));

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
