<?php

declare(strict_types=1);

namespace Tilttek\RpgCalendarEvents\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\RpgCalendarEvents\Entry;
use Tilttek\RpgCalendarEvents\EntryReader;
use Tilttek\RpgCalendarEvents\Exception\InvalidEntry;
use Tilttek\RpgCalendarEvents\Status;

#[CoversClass(EntryReader::class)]
final class EntryReaderTest extends TestCase
{
    public function testItReadsARow(): void
    {
        $entry = new EntryReader()->read(self::row() + ['tags' => ['computing', 'work'], 'days' => 2, 'status' => 'to-verify']);

        self::assertSame('sysadmin-day', $entry->id);
        self::assertSame(['en' => 'System Administrator Appreciation Day', 'fr' => 'Journée des administrateurs système'], $entry->names);
        self::assertSame('last friday of july', (string) $entry->rule);
        self::assertSame('https://example.org/sysadmin', $entry->source);
        self::assertSame('worldwide', $entry->community);
        self::assertSame('2026-11-21 00:00:00 UTC', $entry->verified->format('Y-m-d H:i:s T'));
        self::assertSame(['computing', 'work'], $entry->tags);
        self::assertSame(2, $entry->days);
        self::assertSame(Status::ToVerify, $entry->status);
    }

    public function testTheOptionalFieldsHaveTheirDefaults(): void
    {
        $entry = new EntryReader()->read(self::row());

        self::assertSame([], $entry->tags);
        self::assertSame(1, $entry->days);
        self::assertSame(Status::Verified, $entry->status);
        self::assertFalse($entry->prompts);
    }

    public function testItReadsAPromptADay(): void
    {
        self::assertTrue(new EntryReader()->read(self::row() + ['days' => 31, 'prompts' => true])->prompts);
        self::assertFalse(new EntryReader()->read(self::row() + ['days' => 31, 'prompts' => false])->prompts);
    }

    public function testItReadsEveryRowInOrder(): void
    {
        $entries = new EntryReader()->readAll([self::row(), ['id' => 'other'] + self::row()]);

        self::assertSame(['sysadmin-day', 'other'], array_map(static fn (Entry $entry): string => $entry->id, $entries));
        self::assertSame([], new EntryReader()->readAll([]));
    }

    /**
     * @return iterable<string, array{array<mixed>, string}>
     */
    public static function refusals(): iterable
    {
        yield 'an unknown field' => [self::row() + ['note' => 'x'], 'The entry "sysadmin-day" has the unknown field "note".'];
        yield 'an unknown field cut at 64 characters' => [self::row() + ['n'.str_repeat('é', 69) => 'x'], 'The entry "sysadmin-day" has the unknown field "n'.str_repeat('é', 63).'".'];
        yield 'an unknown numbered field' => [self::row() + [7 => 'x'], 'The entry "sysadmin-day" has the unknown field "7".'];
        yield 'no identifier' => [array_diff_key(self::row(), ['id' => true]), 'The entry "?" has no "id" text.'];
        yield 'an identifier that is not a text' => [['id' => 12] + self::row(), 'The entry "?" has no "id" text.'];
        yield 'an identifier cut at 100 characters' => [['id' => 'X'.str_repeat('é', 119), 'note' => 'x'] + self::row(), 'The entry "X'.str_repeat('é', 99).'" has the unknown field "note".'];
        yield 'no rule' => [array_diff_key(self::row(), ['rule' => true]), 'The entry "sysadmin-day" has no "rule" text.'];
        yield 'a rule that does not read' => [['rule' => 'every blue moon'] + self::row(), 'The entry "sysadmin-day" has a rule that cannot be read: "every blue moon" is not a rule the almanac reads.'];
        yield 'no source' => [array_diff_key(self::row(), ['source' => true]), 'The entry "sysadmin-day" has no "source" text.'];
        yield 'no community' => [array_diff_key(self::row(), ['community' => true]), 'The entry "sysadmin-day" has no "community" text.'];
        yield 'no date of verification' => [array_diff_key(self::row(), ['verified' => true]), 'The entry "sysadmin-day" has no "verified" text.'];
        yield 'a date that does not exist' => [['verified' => '2026-02-30'] + self::row(), 'The entry "sysadmin-day" was verified on "2026-02-30", which is not a date such as 2026-11-21.'];
        yield 'a date with a time' => [['verified' => '2026-11-21 10:00'] + self::row(), 'The entry "sysadmin-day" was verified on "2026-11-21 10:00", which is not a date such as 2026-11-21.'];
        yield 'a date that is not one' => [['verified' => 'yesterday'] + self::row(), 'The entry "sysadmin-day" was verified on "yesterday", which is not a date such as 2026-11-21.'];
        yield 'a date cut at 20 characters' => [['verified' => '9'.str_repeat('é', 29)] + self::row(), 'The entry "sysadmin-day" was verified on "9'.str_repeat('é', 19).'", which is not a date such as 2026-11-21.'];
        yield 'no names' => [array_diff_key(self::row(), ['names' => true]), 'The entry "sysadmin-day" has no "names" by locale.'];
        yield 'a name without locale' => [['names' => ['Day', 'Jour']] + self::row(), 'The entry "sysadmin-day" has a name that is not a text under its locale.'];
        yield 'a name that is not a text' => [['names' => ['en' => 'Day', 'fr' => 4]] + self::row(), 'The entry "sysadmin-day" has a name that is not a text under its locale.'];
        yield 'tags that are a text' => [self::row() + ['tags' => 'computing'], 'The entry "sysadmin-day" has "tags" that are not a list of texts.'];
        yield 'tags by key' => [self::row() + ['tags' => ['a' => 'computing']], 'The entry "sysadmin-day" has "tags" that are not a list of texts.'];
        yield 'a tag that is a number' => [self::row() + ['tags' => ['computing', 3]], 'The entry "sysadmin-day" has "tags" that are not a list of texts.'];
        yield 'days as text' => [self::row() + ['days' => '2'], 'The entry "sysadmin-day" has "days" that is not a whole number.'];
        yield 'an unknown status' => [self::row() + ['status' => 'maybe'], 'The entry "sysadmin-day" has a status that is neither "verified" nor "to-verify".'];
        yield 'a status that is not a text' => [self::row() + ['status' => true], 'The entry "sysadmin-day" has no "status" text.'];
        yield 'prompts as text' => [self::row() + ['days' => 31, 'prompts' => 'yes'], 'The entry "sysadmin-day" has "prompts" that is neither true nor false.'];
        yield 'an entry the constructor refuses' => [['source' => 'http://example.org'] + self::row(), 'The entry "sysadmin-day" has no source: an https address on a host name, of at most 2048 bytes, without spaces.'];
    }

    /**
     * @param array<mixed> $row
     */
    #[DataProvider('refusals')]
    public function testItRefusesARowThatIsNotAnEntry(array $row, string $message): void
    {
        $this->expectException(InvalidEntry::class);
        $this->expectExceptionMessageIsOrContains($message);

        new EntryReader()->read($row);
    }

    public function testTheRuleMessageKeepsThePreviousException(): void
    {
        try {
            new EntryReader()->read(['rule' => 'never'] + self::row());
            self::fail('No exception.');
        } catch (InvalidEntry $invalidEntry) {
            self::assertSame('The entry "sysadmin-day" has a rule that cannot be read: "never" is not a rule the almanac reads.', $invalidEntry->getMessage());
        }
    }

    /**
     * @return array<string, mixed>
     */
    private static function row(): array
    {
        return [
            'id' => 'sysadmin-day',
            'names' => ['en' => 'System Administrator Appreciation Day', 'fr' => 'Journée des administrateurs système'],
            'rule' => 'last friday of july',
            'source' => 'https://example.org/sysadmin',
            'community' => 'worldwide',
            'verified' => '2026-11-21',
        ];
    }
}
