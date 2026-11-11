<?php

declare(strict_types=1);

namespace Tilttek\Quests\Tests\Unit;

use DateTimeImmutable;
use DateTimeZone;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\Quests\Admission;
use Tilttek\Quests\Exception\InvalidQuest;
use Tilttek\Quests\QuestKind;
use Tilttek\Quests\QuestReader;
use Tilttek\Quests\Reward;
use Tilttek\Quests\Schedule\Dated;
use Tilttek\Quests\Schedule\Occurrence;
use Tilttek\Quests\Schedule\Permanent;
use Tilttek\Quests\Schedule\Schedule;
use Tilttek\Quests\Schedule\ScheduleReader;
use Tilttek\Quests\Step;
use Tilttek\Quests\StepMeasure;

#[CoversClass(QuestReader::class)]
final class QuestReaderTest extends TestCase
{
    public function testAQuestIsReadFromPlainValues(): void
    {
        $reader = new QuestReader('America/Toronto');
        $quest = $reader->read('winter', [
            'kind' => 'community',
            'steps' => [
                ['code' => 'play', 'verbs' => ['game.played', 'game.*'], 'count' => 3, 'context' => ['system' => 'dnd5e', 'ranked' => true, 'level' => 2]],
                ['verbs' => ['book.read'], 'total' => 300],
                ['verbs' => ['sheet.published']],
            ],
            'ordered' => true,
            'admission' => 'accept',
            'opens' => '2026-12-01',
            'closes' => '2027-01-01',
            'after' => 'autumn',
            'rewards' => [['kind' => 'points', 'code' => 'xp', 'amount' => 50], ['kind' => 'badge', 'code' => 'explorer']],
        ]);

        self::assertSame('America/Toronto', $reader->timezone()->getName());
        self::assertSame('winter', $quest->code);
        self::assertSame(QuestKind::Community, $quest->kind);
        self::assertTrue($quest->ordered);
        self::assertSame(Admission::OnAcceptance, $quest->admission);
        self::assertSame('autumn', $quest->after);
        self::assertEquals([new Reward('points', 'xp', 50), new Reward('badge', 'explorer')], $quest->rewards);
        self::assertEquals(new Dated(new DateTimeImmutable('2026-12-01', new DateTimeZone('America/Toronto')), new DateTimeImmutable('2027-01-01', new DateTimeZone('America/Toronto'))), $quest->schedule);
        self::assertSame(['play', 'step-2', 'step-3'], array_map(static fn (Step $step): string => $step->code, $quest->steps));
        self::assertSame([3, 300, 1], array_map(static fn (Step $step): int => $step->target, $quest->steps));
        self::assertSame([StepMeasure::Count, StepMeasure::Total, StepMeasure::Count], array_map(static fn (Step $step): StepMeasure => $step->measure, $quest->steps));
        self::assertSame(['game.played', 'game.*'], $quest->steps[0]->scope->patterns);
        self::assertSame(['level' => 2, 'ranked' => true, 'system' => 'dnd5e'], $quest->steps[0]->scope->conditions->values);
    }

    public function testTheDefaultsMakeAPermanentPersonalQuest(): void
    {
        $quest = new QuestReader()->read('q', ['steps' => [['verbs' => ['a.b']]]]);

        self::assertSame(QuestKind::Personal, $quest->kind);
        self::assertFalse($quest->ordered);
        self::assertSame(Admission::Automatic, $quest->admission);
        self::assertNull($quest->after);
        self::assertSame([], $quest->rewards);
        self::assertNull($quest->duelLength);
        self::assertInstanceOf(Permanent::class, $quest->schedule);
        self::assertSame('UTC', new QuestReader()->timezone()->getName());
    }

    public function testAQuestOpenOnlyFromOrUntilADateIsDated(): void
    {
        $reader = new QuestReader();

        self::assertEquals(new Dated(new DateTimeImmutable('2026-12-01 UTC'), null), $reader->read('q', ['opens' => '2026-12-01', 'steps' => [['verbs' => ['a.b']]]])->schedule);
        self::assertEquals(new Dated(null, new DateTimeImmutable('2026-12-01 UTC')), $reader->read('q', ['closes' => '2026-12-01', 'steps' => [['verbs' => ['a.b']]]])->schedule);
    }

    public function testADuelIsReadWithItsLength(): void
    {
        $quest = new QuestReader()->read('race', ['kind' => 'duel', 'duel' => ['length' => 'P3D'], 'steps' => [['verbs' => ['a.b']]]]);

        self::assertSame(3, $quest->duelLength?->d);
    }

    public function testScheduleReadersAddKeysOfTheirOwn(): void
    {
        $yearly = new class implements ScheduleReader {
            #[Override]
            public function keys(): array
            {
                return ['every'];
            }

            #[Override]
            public function read(array $definition, DateTimeZone $timezone): ?Schedule
            {
                if (!isset($definition['every'])) {
                    return null;
                }

                return new class($timezone) implements Schedule {
                    public function __construct(public DateTimeZone $timezone)
                    {
                    }

                    #[Override]
                    public function occurrenceAt(DateTimeImmutable $moment): Occurrence
                    {
                        return new Occurrence($moment->setTimezone($this->timezone)->format('Y'));
                    }
                };
            }
        };
        $reader = new QuestReader('Europe/Paris', [$yearly]);

        $quest = $reader->read('q', ['every' => 'year', 'steps' => [['verbs' => ['a.b']]]]);
        self::assertSame('2027', $quest->schedule->occurrenceAt(new DateTimeImmutable('2026-12-31 23:30 UTC'))?->code);
        self::assertInstanceOf(Permanent::class, $reader->read('q', ['steps' => [['verbs' => ['a.b']]]])->schedule);
        self::assertInstanceOf(Dated::class, $reader->read('q', ['opens' => '2026-12-01', 'steps' => [['verbs' => ['a.b']]]])->schedule);
    }

    /**
     * @return iterable<string, array{array<array-key, mixed>, string}>
     */
    public static function refusals(): iterable
    {
        $step = [['verbs' => ['a.b']]];

        yield 'unknown key' => [['steps' => $step, 'colour' => 'red', 'size' => 2], 'The quest "q" has keys no quest has: colour, size.'];
        yield 'unknown kind' => [['kind' => 'guild', 'steps' => $step], 'The kind of "q" is personal, community, team or duel.'];
        yield 'kind not a string' => [['kind' => 3, 'steps' => $step], 'The "kind" of "q" is a string.'];
        yield 'unknown admission' => [['admission' => 'invite', 'steps' => $step], 'The admission of "q" is automatic or accept.'];
        yield 'duel not a list' => [['kind' => 'duel', 'duel' => 'P3D', 'steps' => $step], 'The duel of "q" holds its length.'];
        yield 'no steps' => [[], 'The steps of "q" are a list.'];
        yield 'steps by name' => [['steps' => ['a' => ['verbs' => ['a.b']]]], 'The steps of "q" are a list.'];
        yield 'step not a list' => [['steps' => ['a.b']], 'A step of "q" holds its code, verbs, context, and count or total.'];
        yield 'step with an unknown key' => [['steps' => [['verbs' => ['a.b'], 'times' => 3]]], 'A step of "q" holds its code, verbs, context, and count or total.'];
        yield 'count and total' => [['steps' => [['verbs' => ['a.b'], 'count' => 2, 'total' => 3]]], 'A step of "q" asks for a count or a total, not both.'];
        yield 'count not an integer' => [['steps' => [['verbs' => ['a.b'], 'count' => '2']]], 'The "count" of "q" is an integer.'];
        yield 'verbs missing' => [['steps' => [['count' => 2]]], 'A step of "q" names its verbs as a list of patterns, and its context as values by name.'];
        yield 'context not a list' => [['steps' => [['verbs' => ['a.b'], 'context' => 'x']]], 'A step of "q" names its verbs as a list of patterns, and its context as values by name.'];
        yield 'verb not a string' => [['steps' => [['verbs' => [3]]]], 'A step of "q" names its verbs as a list of patterns.'];
        yield 'context by position' => [['steps' => [['verbs' => ['a.b'], 'context' => ['x']]]], 'The context of a step of "q" holds integers, booleans and strings by name.'];
        yield 'context holding a list' => [['steps' => [['verbs' => ['a.b'], 'context' => ['x' => []]]]], 'The context of a step of "q" holds integers, booleans and strings by name.'];
        yield 'rewards by name' => [['steps' => $step, 'rewards' => ['a' => []]], 'The rewards of "q" are a list.'];
        yield 'rewards not a list' => [['steps' => $step, 'rewards' => 'xp'], 'The rewards of "q" are a list.'];
        yield 'reward not a list' => [['steps' => $step, 'rewards' => ['xp']], 'A reward of "q" holds its kind, code and amount.'];
        yield 'reward without kind' => [['steps' => $step, 'rewards' => [['code' => 'xp']]], '"" is not a reward kind code: 1 to 64 lower-case letters, digits, "_", "." or "-", starting with a letter.'];
        yield 'reward without code' => [['steps' => $step, 'rewards' => [['kind' => 'points']]], 'What a reward names is 1 to 255 bytes without whitespace or control characters; "" is not.'];
        yield 'ordered not a boolean' => [['steps' => $step, 'ordered' => 'yes'], 'The "ordered" of "q" is true or false.'];
        yield 'not a date' => [['steps' => $step, 'opens' => 'someday'], 'The "opens" of "q" is a date; "someday" is not.'];
    }

    /**
     * @param array<array-key, mixed> $definition
     */
    #[DataProvider('refusals')]
    public function testAWrongDefinitionIsRefused(array $definition, string $message): void
    {
        $this->expectException(InvalidQuest::class);
        $this->expectExceptionMessageIsOrContains($message);

        new QuestReader()->read('q', $definition);
    }

    public function testAnUnknownTimezoneIsRefused(): void
    {
        $this->expectException(InvalidQuest::class);
        $this->expectExceptionMessageIsOrContains('"Mars/Olympus" is not a timezone.');

        new QuestReader('Mars/Olympus');
    }
}
