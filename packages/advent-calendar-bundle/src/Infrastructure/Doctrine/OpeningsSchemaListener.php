<?php

declare(strict_types=1);

namespace Tilttek\AdventCalendarBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;

/**
 * Adds the table of the openings to the schema the ORM generates for its connection: `doctrine:migrations:diff` then
 * writes the migration that creates it, and `doctrine:schema:validate` does not ask to drop it.
 */
final readonly class OpeningsSchemaListener
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

        $event->setSchema(AdventCalendarTables::configure($event->getSchema()));
    }
}
