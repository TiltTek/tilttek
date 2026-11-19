<?php

declare(strict_types=1);

namespace Tilttek\ExchangeBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;

/**
 * Adds the exchanges' tables to the schema the ORM generates for their connection: `doctrine:migrations:diff` then
 * writes the migration that creates them, and `doctrine:schema:validate` does not ask to drop them.
 */
final readonly class ExchangeSchemaListener
{
    public function __construct(
        private Connection $connection,
        private bool $catalog,
    ) {
    }

    public function postGenerateSchema(GenerateSchemaEventArgs $event): void
    {
        if ($event->getEntityManager()->getConnection() !== $this->connection) {
            return;
        }

        $event->setSchema(ExchangeTables::configure($event->getSchema(), $this->catalog));
    }
}
