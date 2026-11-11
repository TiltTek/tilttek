<?php

declare(strict_types=1);

namespace Tilttek\CollectionsBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;

/**
 * Adds the collections' tables to the schema the ORM generates for their connection: `doctrine:migrations:diff`
 * then writes the migration that creates them, and `doctrine:schema:validate` does not ask to drop them.
 */
final readonly class CollectionsSchemaListener
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    public function postGenerateSchema(GenerateSchemaEventArgs $event): void
    {
        if ($event->getEntityManager()->getConnection() !== $this->connection) {
            return;
        }

        $event->setSchema(CollectionsTables::configure($event->getSchema()));
    }
}
