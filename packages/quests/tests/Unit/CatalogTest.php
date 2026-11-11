<?php

declare(strict_types=1);

namespace Tilttek\Quests\Tests\Unit;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\Quests\Chains;
use Tilttek\Quests\Exception\InvalidQuest;
use Tilttek\Quests\Exception\UnknownQuest;
use Tilttek\Quests\InMemoryCatalog;
use Tilttek\Quests\Quest;
use Tilttek\Quests\QuestKind;
use Tilttek\Quests\QuestReader;
use Tilttek\Quests\Scope;
use Tilttek\Quests\Step;

#[CoversClass(InMemoryCatalog::class)]
#[CoversClass(Chains::class)]
final class CatalogTest extends TestCase
{
    public function testTheQuestsAreKeptByCode(): void
    {
        $catalog = new InMemoryCatalog([self::quest('b'), self::quest('a'), self::quest('c', 'b')]);

        self::assertSame(['a', 'b', 'c'], array_map(static fn (Quest $quest): string => $quest->code, $catalog->all()));
        self::assertTrue($catalog->has('a'));
        self::assertFalse($catalog->has('d'));
        self::assertSame('c', $catalog->get('c')->code);
        self::assertSame([], new InMemoryCatalog()->all());
    }

    public function testTheQuestsAreReadFromAConfiguration(): void
    {
        $catalog = InMemoryCatalog::fromArray([
            'first' => ['steps' => [['verbs' => ['a.b']]], 'opens' => '2026-12-01 00:00'],
            'second' => ['after' => 'first', 'steps' => [['verbs' => ['a.b']]]],
        ], new QuestReader('Asia/Tokyo'));

        self::assertSame('second', $catalog->get('second')->code);
        self::assertNull($catalog->get('first')->schedule->occurrenceAt(new DateTimeImmutable('2026-11-30 14:59 UTC')));
        self::assertNotNull($catalog->get('first')->schedule->occurrenceAt(new DateTimeImmutable('2026-11-30 15:00 UTC')));
    }

    public function testAnUnknownQuestIsReported(): void
    {
        $this->expectException(UnknownQuest::class);
        $this->expectExceptionMessageIsOrContains('No quest is named "d".');

        new InMemoryCatalog([self::quest('a')])->get('d');
    }

    /**
     * @return iterable<string, array{list<Quest>, string}>
     */
    public static function refusals(): iterable
    {
        yield 'two quests with a code' => [[self::quest('a'), self::quest('a')], 'Two quests are named "a".'];
        yield 'a missing predecessor' => [[self::quest('a', 'z')], '"a" follows "z", which no quest is.'];
        yield 'a predecessor of another kind' => [[self::quest('a'), self::quest('b', 'a', QuestKind::Community)], '"b" follows "a", a quest of another kind.'];
        yield 'a loop' => [[self::quest('a', 'c'), self::quest('b', 'a'), self::quest('c', 'b')], 'The chain of "a" loops back to "a".'];
        yield 'a loop further down' => [[self::quest('d', 'a'), self::quest('a', 'b'), self::quest('b', 'a')], 'The chain of "d" loops back to "a".'];
    }

    /**
     * @param list<Quest> $quests
     */
    #[DataProvider('refusals')]
    public function testAWrongSetOfQuestsIsRefused(array $quests, string $message): void
    {
        $this->expectException(InvalidQuest::class);
        $this->expectExceptionMessageIsOrContains($message);

        new InMemoryCatalog($quests);
    }

    public function testAQuestUnderANumberIsRefused(): void
    {
        $this->expectException(InvalidQuest::class);
        $this->expectExceptionMessageIsOrContains('"7" is not a quest code');

        InMemoryCatalog::fromArray([7 => ['steps' => [['verbs' => ['a.b']]]]]);
    }

    public function testADefinitionThatIsNotAListIsRefused(): void
    {
        $this->expectException(InvalidQuest::class);
        $this->expectExceptionMessageIsOrContains('The quest "a" is a list of values.');

        InMemoryCatalog::fromArray(['a' => 'game.played']);
    }

    private static function quest(string $code, ?string $after = null, QuestKind $kind = QuestKind::Personal): Quest
    {
        return new Quest($code, [new Step('one', new Scope(['a.b']))], $kind, after: $after);
    }
}
