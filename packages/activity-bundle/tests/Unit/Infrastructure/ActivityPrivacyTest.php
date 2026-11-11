<?php

declare(strict_types=1);

namespace Tilttek\ActivityBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Override;
use Phil\PrivacyBundle\Application\Port\PersonalDataEraser;
use Phil\PrivacyBundle\Domain\Retention\ErasureEffect;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\Activity\Activity;
use Tilttek\Activity\Entry;
use Tilttek\Activity\Journal;
use Tilttek\Activity\Log\InMemoryActivityLog;
use Tilttek\ActivityBundle\Infrastructure\Privacy\ActivityPrivacy;
use Tilttek\ActivityBundle\Tests\Unit\Fixture\Clock;

#[CoversClass(ActivityPrivacy::class)]
final class ActivityPrivacyTest extends TestCase
{
    #[Override]
    protected function setUp(): void
    {
        if (!interface_exists(PersonalDataEraser::class)) {
            self::markTestSkipped('phil/privacy-bundle is not installed: the export and erasure of a member are an optional integration.');
        }
    }

    public function testAMembersActivityIsExportedInBatches(): void
    {
        $journal = $this->journal();
        $privacy = new ActivityPrivacy($journal, 'member', 2);

        self::assertSame('tilttek_activity', $privacy->module()->value);
        self::assertSame([], $privacy->export(DataSubject::fromString('9')));

        $export = $privacy->export(DataSubject::fromString('1'));
        self::assertSame([1, 3, 4, 5], $this->positions($export));
        self::assertSame([
            'position' => 5,
            'kind' => 'cancelled',
            'written_at' => '2026-11-07T12:00:00+00:00',
            'key' => 'a',
            'member' => 'member:1',
            'verb' => 'game.played',
            'occurred_at' => '2026-11-01T10:00:00+00:00',
            'object' => 'game:1',
            'quantity' => 1,
            'context' => [],
        ], ($export['activity'] ?? [])[3] ?? null);

        self::assertSame([1, 3, 4, 5], $this->positions(new ActivityPrivacy($journal, 'member', 4)->export(DataSubject::fromString('1'))));
        self::assertSame([1, 3, 4, 5], $this->positions(new ActivityPrivacy($journal, 'member', 500)->export(DataSubject::fromString('1'))));
        self::assertSame([6], $this->positions(new ActivityPrivacy($journal, 'user', 500)->export(DataSubject::fromString('1'))));
    }

    public function testAMembersActivityIsErased(): void
    {
        $journal = $this->journal();
        $report = new ActivityPrivacy($journal, 'member', 500)->erase(DataSubject::fromString('1'));

        self::assertSame('tilttek_activity', $report->module->value);
        self::assertSame(['tilttek_activity_entry' => ErasureEffect::Deleted, 'tilttek_activity_entry.object' => ErasureEffect::Redacted], $report->effects);
        self::assertSame([2, 6], array_map(static fn (Entry $entry): int => $entry->position, $journal->read(0, 10)));
        self::assertNull($journal->read(0, 10)[0]->activity->object);
    }

    /**
     * @param array{activity?: list<array{position: int, ...}>, ...} $export
     *
     * @return list<int>
     */
    private function positions(array $export): array
    {
        return array_column($export['activity'] ?? [], 'position');
    }

    private function journal(): Journal
    {
        $journal = new Journal(new InMemoryActivityLog(), new Clock());
        $journal->record(Activity::of('a', 'member:1', 'game.played', new DateTimeImmutable('2026-11-01 10:00:00 UTC'), 'game:1'));
        $journal->record(Activity::of('b', 'member:2', 'kudos.given', new DateTimeImmutable('2026-11-02 10:00:00 UTC'), 'member:1'));
        $journal->record(Activity::of('c', 'member:1', 'news.read', new DateTimeImmutable('2026-11-03 10:00:00 UTC')));
        $journal->record(Activity::of('d', 'member:1', 'news.read', new DateTimeImmutable('2026-11-04 10:00:00 UTC')));
        $journal->cancel('a');
        $journal->record(Activity::of('e', 'user:1', 'news.read', new DateTimeImmutable('2026-11-04 10:00:00 UTC')));

        return $journal;
    }
}
