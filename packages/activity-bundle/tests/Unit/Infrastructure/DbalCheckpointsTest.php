<?php

declare(strict_types=1);

namespace Tilttek\ActivityBundle\Tests\Unit\Infrastructure;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\ActivityBundle\Infrastructure\Doctrine\DbalCheckpoints;
use Tilttek\ActivityBundle\Tests\Unit\Fixture\Clock;
use UnexpectedValueException;

#[CoversClass(DbalCheckpoints::class)]
final class DbalCheckpointsTest extends TestCase
{
    public function testACheckpointIsInsertedThenUpdated(): void
    {
        $connection = Database::connection();
        $clock = new Clock('2026-11-07 12:00:00 UTC');
        $checkpoints = new DbalCheckpoints($connection, $clock);

        self::assertSame(0, $checkpoints->position('points'));
        $checkpoints->save('points', 12);
        self::assertSame(12, $checkpoints->position('points'));
        self::assertSame([['projection' => 'points', 'position' => 12, 'updated_at' => '2026-11-07 12:00:00']], $connection->fetchAllAssociative('SELECT * FROM tilttek_activity_checkpoint'));

        $clock->set('2026-11-07 13:00:00 UTC');
        $checkpoints->save('points', 40);
        $checkpoints->save('badges', 3);
        self::assertSame(40, $checkpoints->position('points'));
        self::assertSame(3, $checkpoints->position('badges'));
        self::assertSame([['projection' => 'points', 'position' => 40, 'updated_at' => '2026-11-07 13:00:00'], ['projection' => 'badges', 'position' => 3, 'updated_at' => '2026-11-07 13:00:00']], $connection->fetchAllAssociative('SELECT * FROM tilttek_activity_checkpoint ORDER BY updated_at, position DESC'));

        $checkpoints->save('points', 40);
        self::assertSame(40, $checkpoints->position('points'));
    }

    public function testAPositionWrittenAsTextIsRead(): void
    {
        $connection = Database::connection();
        $connection->executeStatement('CREATE TABLE text_checkpoint (projection TEXT, position TEXT, updated_at TEXT)');
        $connection->executeStatement('DROP TABLE tilttek_activity_checkpoint');
        $connection->executeStatement('ALTER TABLE text_checkpoint RENAME TO tilttek_activity_checkpoint');
        $connection->executeStatement("INSERT INTO tilttek_activity_checkpoint VALUES ('points', '17', '2026-11-07 12:00:00'), ('broken', 'x7', '2026-11-07 12:00:00'), ('trailing', '7x', '2026-11-07 12:00:00'), ('newline', '7\n', '2026-11-07 12:00:00'), ('nothing', NULL, '2026-11-07 12:00:00')");

        $checkpoints = new DbalCheckpoints($connection, new Clock());

        self::assertSame(17, $checkpoints->position('points'));

        foreach (['broken' => 'string', 'trailing' => 'string', 'newline' => 'string', 'nothing' => 'null'] as $projection => $type) {
            try {
                $checkpoints->position($projection);
                self::fail('The checkpoint of '.$projection.' was read.');
            } catch (UnexpectedValueException $exception) {
                self::assertSame('The checkpoint of "'.$projection.'" holds '.$type.'.', $exception->getMessage());
            }
        }
    }
}
