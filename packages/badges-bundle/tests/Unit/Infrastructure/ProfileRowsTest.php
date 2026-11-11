<?php

declare(strict_types=1);

namespace Tilttek\BadgesBundle\Tests\Unit\Infrastructure;

use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\BadgesBundle\Infrastructure\Doctrine\DbalProfileStore;
use UnexpectedValueException;

/**
 * A profile row written by something else than the store, whose JSON is not a list of codes, is refused.
 */
#[CoversClass(DbalProfileStore::class)]
final class ProfileRowsTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function rows(): iterable
    {
        yield 'a string' => ['"gold"'];
        yield 'a number in the list' => ['["gold", 1]'];
        yield 'a list in the list' => ['[["gold"]]'];
    }

    #[DataProvider('rows')]
    public function testARowThatIsNoListOfCodesIsRefused(string $shown): void
    {
        $connection = Database::connection();
        $connection->executeStatement("INSERT INTO tilttek_badges_profile (member, shown, featured, title) VALUES ('member:1', ?, '[]', NULL)", [$shown]);

        $this->expectExceptionObject(new UnexpectedValueException('A profile column holds a list of badge codes.'));
        new DbalProfileStore($connection)->get(ResourceReference::of('member', '1'));
    }
}
