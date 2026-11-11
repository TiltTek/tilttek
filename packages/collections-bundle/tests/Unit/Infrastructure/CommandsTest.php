<?php

declare(strict_types=1);

namespace Tilttek\CollectionsBundle\Tests\Unit\Infrastructure;

use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Tilttek\Collections\CollectionKey;
use Tilttek\Collections\Copy;
use Tilttek\Collections\Daily\DailyPick;
use Tilttek\Collections\Daily\PickOrigin;
use Tilttek\Collections\Source;
use Tilttek\CollectionsBundle\Infrastructure\Console\AlbumCommand;
use Tilttek\CollectionsBundle\Infrastructure\Console\DailyCommand;
use Tilttek\CollectionsBundle\Infrastructure\Console\GrantCommand;
use Tilttek\CollectionsBundle\Tests\Unit\Fixture\Kit;

#[CoversClass(DailyCommand::class)]
#[CoversClass(GrantCommand::class)]
#[CoversClass(AlbumCommand::class)]
final class CommandsTest extends TestCase
{
    public function testTheCalendarIsGeneratedForThirtyDaysFromToday(): void
    {
        $kit = Kit::standard();
        $kit->clock->set('2026-11-06 23:30:00 UTC');
        $kit->picks->save(new DailyPick('2026-11-08', 'deckhand', PickOrigin::Drawn));

        $tester = $this->tester($kit, 'tilttek:collections:daily');

        self::assertSame(0, $tester->execute([]));
        $lines = array_map(static fn (string $line): string => preg_replace('/\s+/', ' ', $line) ?? '', $this->lines($tester, '/^(Day|2026-|2027-)/'));
        self::assertCount(31, $lines);
        self::assertSame('Day Item Why', $lines[0]);
        self::assertSame('2026-11-06 '.$kit->calendar->compute('2026-11-06')->item.' drawn', $lines[1]);
        self::assertSame('2026-11-07 pirate rule', $lines[2]);
        self::assertSame('2026-11-08 deckhand drawn', $lines[3]);
        self::assertStringStartsWith('2026-12-05', $lines[30]);
        self::assertCount(30, $kit->picks->between('2026-11-06', '2026-12-05'));
    }

    public function testTheCalendarIsGeneratedBetweenTwoDaysAndRegenerated(): void
    {
        $kit = Kit::standard();
        $kit->picks->save(new DailyPick('2026-12-02', 'deckhand', PickOrigin::Drawn));

        $tester = $this->tester($kit, 'tilttek:collections:daily');

        self::assertSame(0, $tester->execute(['--from' => '2026-12-01', '--until' => '2026-12-02']));
        self::assertSame(['2026-12-01', '2026-12-02'], array_map(static fn (string $line): string => substr($line, 0, 10), $this->lines($tester, '/^2026-/')));
        self::assertSame('deckhand', $kit->calendar->pick('2026-12-02')->item);

        self::assertSame(0, $tester->execute(['--from' => '2026-12-01', '--until' => '2026-12-02', '--regenerate' => true]));
        self::assertSame($kit->calendar->compute('2026-12-02')->item, $kit->calendar->pick('2026-12-02')->item);
        self::assertSame(0, $tester->execute(['--from' => '2026-12-31']));
        self::assertSame('2027-01-29', $kit->picks->between('2027-01-29', '2027-01-30')[0]->day ?? '');
        self::assertSame([], $kit->picks->between('2027-01-30', '2027-01-30'));

        self::assertSame(1, $tester->execute(['--from' => '2026-02-30']));
        self::assertStringContainsString('"2026-02-30" is not a day written Y-m-d.', $tester->getDisplay());
    }

    public function testACopyIsGiven(): void
    {
        $kit = Kit::standard();
        $tester = $this->tester($kit, 'tilttek:collections:grant');

        self::assertSame(0, $tester->execute(['member' => 'member:1', 'item' => 'pirate', '--key' => 'contest:1']));
        $copy = $kit->collections->copiesOf(ResourceReference::fromString('member:1'))[0] ?? self::fail('No copy.');
        self::assertStringContainsString(\sprintf('member:1 owns the copy %s of pirate.', $copy->id), $tester->getDisplay());
        self::assertSame([Source::Granted, true], [$copy->source, $copy->tradable]);

        self::assertSame(0, $tester->execute(['member' => 'member:1', 'item' => 'deckhand', '--key' => 'gift:1', '--source' => 'gift', '--bound' => true]));
        $gift = $kit->collections->copy(Copy::idFor(CollectionKey::fromString('gift:1')));
        self::assertSame([Source::Gift, false], [$gift->source, $gift->tradable]);

        foreach ([[['member' => 'member:1', 'item' => 'pirate', '--key' => 'k', '--source' => 'stolen'], '"stolen" is not a source: welcome, daily, pack, reward, exchange, purchase, gift, granted.'], [['member' => 'nobody', 'item' => 'pirate', '--key' => 'k'], 'is not a resource reference'], [['member' => 'member:1', 'item' => 'parrot', '--key' => 'k'], 'No item is named "parrot".'], [['member' => 'member:1', 'item' => 'pirate'], 'A collection key is 1 to 200 bytes; this one is 0.']] as [$input, $message]) {
            self::assertSame(1, $tester->execute($input));
            self::assertStringContainsString($message, preg_replace('/\s+/', ' ', $tester->getDisplay()) ?? '');
        }

        self::assertCount(2, $kit->collections->copiesOf(ResourceReference::fromString('member:1')));
    }

    public function testAnAlbumIsShown(): void
    {
        $kit = Kit::standard();
        $member = ResourceReference::fromString('member:1');
        $kit->collections->grant($member, 'pirate', Source::Granted, 'k1');
        $kit->collections->grant($member, 'pirate', Source::Granted, 'k2');
        $kit->collections->grant($member, 'deckhand', Source::Granted, 'k3');

        $tester = $this->tester($kit, 'tilttek:collections:album');

        self::assertSame(0, $tester->execute(['member' => 'member:1']));
        self::assertSame([
            'Set     Owned   Completed once',
            'crew    2 / 2   yes',
            'ember   0 / 1   no',
            'Item       Copies   Doubles',
            'pirate     2        1',
            'deckhand   1        0',
        ], $this->lines($tester, '/^(Set|crew|ember|Item|pirate|deckhand)\b/'));

        self::assertSame(1, $tester->execute(['member' => 'nobody']));
        self::assertStringContainsString('[ERROR]', $tester->getDisplay());
    }

    /**
     * @return list<string>
     */
    private function lines(CommandTester $tester, string $pattern): array
    {
        return array_values(array_filter(array_map(trim(...), explode("\n", $tester->getDisplay())), static fn (string $line): bool => 1 === preg_match($pattern, $line)));
    }

    private function tester(Kit $kit, string $name): CommandTester
    {
        $application = new Application();
        $application->addCommand(new DailyCommand($kit->calendar, $kit->clock));
        $application->addCommand(new GrantCommand($kit->collections));
        $application->addCommand(new AlbumCommand($kit->collections));

        return new CommandTester($application->find($name));
    }
}
