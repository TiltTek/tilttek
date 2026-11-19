<?php

declare(strict_types=1);

namespace Tilttek\LuckTokensBundle\Tests\Integration;

use Composer\Autoload\ClassLoader;
use Doctrine\DBAL\Connection;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Component\Process\Process;
use Tilttek\LuckTokens\Ledger\LedgerEntry;
use Tilttek\LuckTokensBundle\Application\LockedLuckTokens;
use Tilttek\LuckTokensBundle\Infrastructure\Doctrine\DbalTokenLedger;
use Tilttek\LuckTokensBundle\Tests\Unit\Fixture\Database;

/**
 * Four processes grant tokens to the same holder of the same table at once, on SQLite in a file, MySQL and PostgreSQL:
 * the ledger ends with every grant once, at positions that follow one another, each balance one more than the one
 * before. Without the locks, the processes meet in the ledger and read it again; with them, they never meet.
 */
#[CoversClass(DbalTokenLedger::class)]
#[CoversClass(LockedLuckTokens::class)]
final class ConcurrencyTest extends TestCase
{
    private const int WORKERS = 4;

    private const int GRANTS = 25;

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function databases(): iterable
    {
        foreach (['sqlite', 'mysql', 'postgresql'] as $name) {
            foreach ([false, true] as $locks) {
                yield $name.($locks ? ' with locks' : '') => [$name, $locks];
            }
        }
    }

    #[DataProvider('databases')]
    public function testEveryGrantIsWrittenOnceInOrder(string $database, bool $locks): void
    {
        $work = sys_get_temp_dir().'/luck-tokens-concurrency-'.getmypid().'-'.hrtime(true);
        mkdir($work);

        try {
            [$target, $connection] = $this->database($database, $work);
            $conflicts = $this->work($target, $locks ? $work : '');
            $entries = new DbalTokenLedger($connection)->entries(ResourceReference::of('table', '1'));
            $connection->close();

            self::assertSame(range(1, self::WORKERS * self::GRANTS), array_map(static fn (LedgerEntry $entry): int => $entry->position, $entries));
            self::assertSame(range(1, self::WORKERS * self::GRANTS), array_map(static fn (LedgerEntry $entry): int => $entry->balance, $entries));
            self::assertCount(self::WORKERS * self::GRANTS, array_unique(array_map(static fn (LedgerEntry $entry): string => $entry->operation, $entries)));

            if ($locks) {
                self::assertSame(0, $conflicts, 'Under the lock of the holder, no process meets another in the ledger.');
            }
        } finally {
            $files = glob($work.'/*');

            foreach (false === $files ? [] : $files as $file) {
                unlink($file);
            }

            rmdir($work);
        }
    }

    /**
     * @return array{string, Connection}
     */
    private function database(string $database, string $work): array
    {
        if ('sqlite' === $database) {
            $path = $work.'/ledger.sqlite';

            return [$path, Database::file($path)];
        }

        $variables = 'mysql' === $database ? ['TILTTEK_LUCK_TOKENS_TEST_MYSQL_URL', 'PHIL_TEST_MYSQL_URL'] : ['TILTTEK_LUCK_TOKENS_TEST_POSTGRESQL_URL', 'PHIL_TEST_POSTGRESQL_URL'];
        $url = Server::url(...$variables) ?? self::markTestSkipped(\sprintf('Set %s, or run phil-dev services, to test against a real server.', implode(' or ', $variables)));

        return [$url, Server::connect($url)];
    }

    /**
     * Runs the workers at once and gives the conflicts they met, all told.
     */
    private function work(string $database, string $locks): int
    {
        $autoload = \dirname((string) new ReflectionClass(ClassLoader::class)->getFileName(), 2).'/autoload.php';
        $processes = [];

        for ($worker = 1; $worker <= self::WORKERS; ++$worker) {
            $process = new Process([\PHP_BINARY, __DIR__.'/Worker/grants.php', $autoload, $database, (string) $worker, (string) self::GRANTS, $locks]);
            $process->start();
            $processes[] = $process;
        }

        $conflicts = 0;

        foreach ($processes as $process) {
            self::assertSame(0, $process->wait(), $process->getErrorOutput());
            $result = json_decode($process->getOutput(), true, 2, \JSON_THROW_ON_ERROR);
            self::assertIsArray($result);
            self::assertIsInt($result['conflicts']);
            $conflicts += $result['conflicts'];
        }

        return $conflicts;
    }
}
