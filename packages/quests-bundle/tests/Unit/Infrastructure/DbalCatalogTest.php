<?php

declare(strict_types=1);

namespace Tilttek\QuestsBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\Quests\Exception\InvalidQuest;
use Tilttek\Quests\Exception\UnknownQuest;
use Tilttek\Quests\Quest;
use Tilttek\Quests\QuestReader;
use Tilttek\QuestsBundle\Infrastructure\Doctrine\DbalCatalog;
use Tilttek\QuestsBundle\Infrastructure\Doctrine\QuestsTables;

#[CoversClass(DbalCatalog::class)]
final class DbalCatalogTest extends TestCase
{
    public function testTheQuestsAreKeptInTheDatabase(): void
    {
        $connection = Database::connection();
        $catalog = new DbalCatalog($connection, new QuestReader('America/Toronto'));

        self::assertFalse($catalog->has('first'));
        $first = $catalog->save('first', ['steps' => [['verbs' => ['game.played']]], 'opens' => '2026-12-01']);
        $catalog->save('second', ['after' => 'first', 'steps' => [['verbs' => ['game.played'], 'count' => 2]]]);
        $catalog->save('first', ['steps' => [['verbs' => ['game.won']]], 'opens' => '2026-12-01']);

        self::assertSame('first', $first->code);
        self::assertSame(['first', 'second'], array_map(static fn (Quest $quest): string => $quest->code, $catalog->all()));
        self::assertTrue($catalog->has('second'));
        self::assertFalse($catalog->has('third'));
        self::assertSame(['game.won'], $catalog->get('first')->patterns());
        self::assertNull($catalog->get('first')->schedule->occurrenceAt(new DateTimeImmutable('2026-12-01 04:59 UTC')));
        self::assertNotNull($catalog->get('first')->schedule->occurrenceAt(new DateTimeImmutable('2026-12-01 05:00 UTC')));
        self::assertSame('{"after":"first","steps":[{"verbs":["game.played"],"count":2}]}', $connection->fetchOne(\sprintf('SELECT definition FROM %s WHERE code = ?', QuestsTables::DEFINITIONS), ['second']));

        $catalog->remove('second');
        self::assertSame(['first'], array_map(static fn (Quest $quest): string => $quest->code, $catalog->all()));
    }

    public function testAChainIsKeptWhole(): void
    {
        $catalog = new DbalCatalog(Database::connection(), new QuestReader());
        $catalog->save('first', ['steps' => [['verbs' => ['game.played']]]]);
        $catalog->save('second', ['after' => 'first', 'steps' => [['verbs' => ['game.played']]]]);

        try {
            $catalog->save('third', ['after' => 'nothing', 'steps' => [['verbs' => ['game.played']]]]);
            self::fail('Expected a refusal.');
        } catch (InvalidQuest $invalidQuest) {
            self::assertSame('"third" follows "nothing", which no quest is.', $invalidQuest->getMessage());
        }

        self::assertFalse($catalog->has('third'));

        $this->expectExceptionObject(InvalidQuest::because('"second" follows "first", which no quest is.'));
        $catalog->remove('first');
    }

    public function testAnUnknownQuestIsReported(): void
    {
        $this->expectExceptionObject(UnknownQuest::named('nothing'));

        new DbalCatalog(Database::connection(), new QuestReader())->get('nothing');
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function brokenDefinitions(): iterable
    {
        yield 'not JSON' => ['{', 'The definition of "broken" is not JSON: Syntax error'];
        yield 'not a list' => ['"a.b"', 'The definition of "broken" is a list of values.'];
        yield 'too deep' => [str_repeat('[', 64).str_repeat(']', 64), 'The definition of "broken" is not JSON: Maximum stack depth exceeded'];
        yield 'as deep as can be' => [str_repeat('[', 63).str_repeat(']', 63), 'The quest "broken" has keys no quest has: 0.'];
    }

    #[DataProvider('brokenDefinitions')]
    public function testABrokenDefinitionIsReported(string $json, string $message): void
    {
        $connection = Database::connection();
        $connection->insert(QuestsTables::DEFINITIONS, ['code' => 'broken', 'definition' => $json]);

        $this->expectExceptionObject(InvalidQuest::because($message));
        new DbalCatalog($connection, new QuestReader())->all();
    }
}
