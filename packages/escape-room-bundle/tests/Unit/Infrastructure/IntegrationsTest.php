<?php

declare(strict_types=1);

namespace Tilttek\EscapeRoomBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Phil\PrivacyBundle\Domain\Retention\ErasureEffect;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Scheduler\Generator\MessageContext;
use Symfony\Component\Scheduler\Trigger\PeriodicalTrigger;
use Tilttek\EscapeRoom\Booking\BookingRefusal;
use Tilttek\EscapeRoom\Booking\SeatBooking;
use Tilttek\EscapeRoom\Booking\Session;
use Tilttek\EscapeRoomBundle\Infrastructure\Console\ExpireCommand;
use Tilttek\EscapeRoomBundle\Infrastructure\Doctrine\EscapeRoomTables;
use Tilttek\EscapeRoomBundle\Infrastructure\Doctrine\Rows;
use Tilttek\EscapeRoomBundle\Infrastructure\Memory\InMemorySeatLedger;
use Tilttek\EscapeRoomBundle\Infrastructure\Privacy\EscapeRoomPrivacy;
use Tilttek\EscapeRoomBundle\Infrastructure\Scheduler\EscapeRoomSchedule;
use Tilttek\EscapeRoomBundle\Infrastructure\Scheduler\ExpireDue;
use Tilttek\EscapeRoomBundle\Infrastructure\Scheduler\ExpireDueHandler;
use Tilttek\EscapeRoomBundle\Infrastructure\Twig\EscapeRoomExtension;
use Tilttek\EscapeRoomBundle\Infrastructure\Twig\KitComponents;
use Tilttek\EscapeRoomBundle\Tests\Unit\Fixture\Desk;
use UnexpectedValueException;

#[CoversClass(EscapeRoomSchedule::class)]
#[CoversClass(ExpireDue::class)]
#[CoversClass(ExpireDueHandler::class)]
#[CoversClass(ExpireCommand::class)]
#[CoversClass(EscapeRoomPrivacy::class)]
#[CoversClass(EscapeRoomTables::class)]
#[CoversClass(Rows::class)]
#[CoversClass(EscapeRoomExtension::class)]
#[CoversClass(KitComponents::class)]
final class IntegrationsTest extends TestCase
{
    public function testTheScheduleSendsAnExpiryAtItsFrequency(): void
    {
        $messages = new EscapeRoomSchedule()->getSchedule()->getRecurringMessages();

        self::assertCount(1, $messages);
        $trigger = $messages[0]->getTrigger();
        self::assertInstanceOf(PeriodicalTrigger::class, $trigger);
        self::assertSame('every 15 seconds', (string) $trigger);
        self::assertSame('every 2 minutes', (string) new EscapeRoomSchedule('2 minutes')->getSchedule()->getRecurringMessages()[0]->getTrigger());
        self::assertEquals([new ExpireDue()], iterator_to_array($messages[0]->getMessages(new MessageContext('n', 'id', $trigger, new DateTimeImmutable()))));
    }

    public function testTheHandlerEndsTheGamesDueByItsBatch(): void
    {
        $kit = new Desk();
        $kit->desk->start('a', 'cell', ResourceReference::of('team', 'a'));
        $kit->desk->start('b', 'cell', ResourceReference::of('team', 'b'));
        $kit->clock->sleep(60);

        self::assertCount(1, new ExpireDueHandler($kit->desk, 1)());
        self::assertCount(1, new ExpireDueHandler($kit->desk)());
        self::assertSame([], new ExpireDueHandler($kit->desk)());
    }

    public function testTheCommandEndsTheGamesDue(): void
    {
        $kit = new Desk();
        $kit->desk->start('a', 'cell', ResourceReference::of('team', 'a'));
        $kit->desk->start('b', 'cell', ResourceReference::of('team', 'b'));
        $kit->clock->sleep(60);

        $application = new Application();
        $application->addCommand(new ExpireCommand($kit->desk));

        $tester = new CommandTester($application->find('tilttek:escape-room:expire'));

        self::assertSame(0, $tester->execute(['--limit' => '1']));
        self::assertStringContainsString('[OK] 1 game(s) ended.', $tester->getDisplay());
        self::assertSame(0, $tester->execute([]));
        self::assertStringContainsString('[OK] 1 game(s) ended.', $tester->getDisplay());
        self::assertSame(2, $tester->execute(['--limit' => '0']));
        self::assertStringContainsString('[ERROR] A batch of expiries holds 1 to 1000 games.', $tester->getDisplay());
    }

    public function testAMemberIsExportedAndErasedEverywhere(): void
    {
        $kit = new Desk();
        $member = ResourceReference::of('member', '7');
        $other = ResourceReference::of('member', '8');
        $ledger = new InMemorySeatLedger();
        $session = new Session('s-1', 'cell', new DateTimeImmutable('2026-11-15 19:00 UTC'), new DateTimeImmutable('2026-11-15 20:00 UTC'));
        $ledger->hold($session, new SeatBooking('b-1', 's-1', $member, 2, false), static fn (array $held): ?BookingRefusal => null);
        $kit->desk->start('g-1', 'cell', $member, 's-1');
        $kit->desk->start('g-2', 'crypt', ResourceReference::of('team', 'owls'));
        $kit->desk->message('g-2', $member, 'Look up');
        $kit->clock->sleep(10);
        $kit->desk->guess('g-1', 'door', 'key');
        $kit->desk->consent('g-1', true);
        $kit->desk->start('g-3', 'cell', $other);

        $privacy = new EscapeRoomPrivacy($kit->games, $kit->results, $kit->screens, 'member', $ledger);

        self::assertSame('tilttek_escape_room', $privacy->module()->value);
        $export = $privacy->export(DataSubject::fromString('7'));
        self::assertSame(['games', 'results', 'screens', 'bookings'], array_keys($export));
        self::assertSame(['g-1'], array_keys($export['games']));
        self::assertSame(['g-1' => ['room' => 'cell', 'session' => 's-1', 'final_seconds' => 10, 'played_seconds' => 10, 'penalty_seconds' => 0, 'hints' => 0, 'skips' => 0, 'escaped_at' => '2026-11-14T20:00:10.000000Z', 'public' => true]], $export['results']);
        self::assertSame(['g-2' => ['kind' => 'message', 'shown_at' => '2026-11-14T20:00:00.000000Z', 'puzzle' => null, 'tier' => null, 'message' => 'Look up']], $export['screens']);
        self::assertSame([['id' => 'b-1', 'session' => 's-1', 'seats' => 2, 'private' => false]], $export['bookings']);

        $report = $privacy->erase(DataSubject::fromString('7'));
        self::assertSame([
            'tilttek_escape_room_game.team' => ErasureEffect::Deleted,
            'tilttek_escape_room_result.team' => ErasureEffect::Deleted,
            'tilttek_escape_room_screen.game' => ErasureEffect::Deleted,
            'tilttek_escape_room_screen.host' => ErasureEffect::Pseudonymised,
            'tilttek_escape_room_booking.party' => ErasureEffect::Pseudonymised,
        ], $report->effects);
        self::assertSame([], $privacy->export(DataSubject::fromString('7')));
        self::assertNull($kit->games->find('g-1'));
        self::assertNull($kit->screens->screen('g-1'));
        self::assertSame([], $kit->results->ofTeam($member));
        self::assertSame([], $kit->desk->leaderboard('cell'));
        $screen = $kit->screens->screen('g-2');
        self::assertNotNull($screen);
        $host = $screen->host;
        self::assertSame('erased', $host->key);
        self::assertMatchesRegularExpression('/^[0-9a-f]{32}$/D', $host->id);
        self::assertSame('Look up', $screen->message);
        self::assertTrue($host->equals($ledger->bookings($session)[0]->party));
        self::assertSame('member:8', $kit->desk->game('g-3')->game->team->toString());
        self::assertSame('team:owls', $kit->desk->game('g-2')->game->team->toString());
    }

    public function testAGameStillRunningKeepsRunningUnderThePseudonym(): void
    {
        $kit = new Desk();
        $member = ResourceReference::of('member', '7');
        $kit->desk->start('g-1', 'cell', $member);
        $kit->desk->consent('g-1', true);
        $kit->desk->message('g-1', ResourceReference::of('member', '9'), 'Look up');
        $kit->desk->start('g-2', 'crypt', ResourceReference::of('team', 'owls'));
        $kit->desk->message('g-2', $member, 'Mind the lamp');
        $kit->desk->abandon('g-2');
        $kit->desk->start('g-3', 'crypt', ResourceReference::of('team', 'bats'));
        $kit->desk->message('g-3', $member, 'Left');
        $kit->games->forget('g-3');

        $report = new EscapeRoomPrivacy($kit->games, $kit->results, $kit->screens, 'member')->erase(DataSubject::fromString('7'));

        self::assertSame([
            'tilttek_escape_room_game.team' => ErasureEffect::Pseudonymised,
            'tilttek_escape_room_result.team' => ErasureEffect::Pseudonymised,
            'tilttek_escape_room_screen.game' => ErasureEffect::Pseudonymised,
            'tilttek_escape_room_screen.host' => ErasureEffect::Deleted,
        ], $report->effects);
        $game = $kit->desk->game('g-1')->game;
        self::assertSame('erased', $game->team->key);
        self::assertFalse($game->public);
        self::assertSame('member:9', $kit->screens->screen('g-1')?->host->toString());
        self::assertNull($kit->screens->screen('g-2'));
        self::assertNull($kit->screens->screen('g-3'));
        self::assertSame([], $kit->games->ofTeam($member));
        $kit->clock->sleep(10);
        $kit->desk->guess('g-1', 'door', 'key');
        self::assertSame([], $kit->desk->leaderboard('cell'));
        $result = $kit->results->ofTeam($game->team)['g-1'] ?? null;
        self::assertNotNull($result);
        self::assertFalse($result->public);
    }

    public function testAnErasureWithoutBookingsLeavesThemOut(): void
    {
        $kit = new Desk();
        $privacy = new EscapeRoomPrivacy($kit->games, $kit->results, $kit->screens, 'member');

        self::assertSame([], $privacy->export(DataSubject::fromString('7')));
        self::assertSame([
            'tilttek_escape_room_game.team' => ErasureEffect::Deleted,
            'tilttek_escape_room_result.team' => ErasureEffect::Deleted,
            'tilttek_escape_room_screen.game' => ErasureEffect::Deleted,
            'tilttek_escape_room_screen.host' => ErasureEffect::Deleted,
        ], $privacy->erase(DataSubject::fromString('7'))->effects);
    }

    public function testTheTablesAreAddedOnce(): void
    {
        $schema = EscapeRoomTables::configure(Schema::editor()->create());

        self::assertSame([EscapeRoomTables::GAMES, EscapeRoomTables::RESULTS, EscapeRoomTables::SCREENS, EscapeRoomTables::BOOKINGS, EscapeRoomTables::SESSION_LOCKS], array_map(static fn (Table $table): string => $table->getObjectName()->toString(), $schema->getTables()));
        self::assertCount(5, EscapeRoomTables::configure($schema)->getTables());
        $games = $schema->getTable(EscapeRoomTables::GAMES);
        self::assertFalse($games->getColumn('session')->getNotnull());
        self::assertFalse($games->getColumn('deadline')->getNotnull());
        self::assertSame(320, $games->getColumn('team')->getLength());
        self::assertSame(1_120, $schema->getTable(EscapeRoomTables::SCREENS)->getColumn('message')->getLength());
        self::assertSame(255, $schema->getTable(EscapeRoomTables::BOOKINGS)->getColumn('id')->getLength());
    }

    public function testTheValuesOfARowAreReadStrictly(): void
    {
        self::assertSame(-12, Rows::integer('-12'));
        self::assertSame(7, Rows::integer(7));
        self::assertTrue(Rows::flag('t'));
        self::assertTrue(Rows::flag(1));
        self::assertTrue(Rows::flag('true'));
        self::assertTrue(Rows::flag('1'));
        self::assertTrue(Rows::flag(true));
        self::assertFalse(Rows::flag('f'));
        self::assertFalse(Rows::flag(0));
        self::assertFalse(Rows::flag('0'));
        self::assertFalse(Rows::flag('false'));
        self::assertFalse(Rows::flag(false));
        self::assertNull(Rows::optionalText(null));
        self::assertSame('2026-11-14 20:00:00 UTC', Rows::date('2026-11-14 20:00:00')->format('Y-m-d H:i:s e'));

        foreach ([static fn (): int => Rows::integer('1.5'), static fn (): int => Rows::integer("1\n"), static fn (): bool => Rows::flag('yes'), static fn (): string => Rows::text(1)] as $read) {
            try {
                $read();
                self::fail('The value is refused.');
            } catch (UnexpectedValueException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testTheInsertionThatIgnoresARowFollowsThePlatform(): void
    {
        self::assertSame('INSERT OR IGNORE INTO t (a) VALUES (?)', Rows::ignoring(new SQLitePlatform(), 'INTO t (a) VALUES (?)', 'a'));
        self::assertSame('INSERT IGNORE INTO t (a) VALUES (?)', Rows::ignoring(new MariaDBPlatform(), 'INTO t (a) VALUES (?)', 'a'));
        self::assertSame('INSERT INTO t (a) VALUES (?) ON CONFLICT (a) DO NOTHING', Rows::ignoring(new PostgreSQLPlatform(), 'INTO t (a) VALUES (?)', 'a'));
        self::assertSame('INSERT INTO t (a) VALUES (?)', Rows::ignoring(new OraclePlatform(), 'INTO t (a) VALUES (?)', 'a'));
    }

    public function testTheTwigFunctions(): void
    {
        $extension = new EscapeRoomExtension(new KitComponents('/ui/kit/'), 'rooms');

        self::assertSame('ui:kit:Card:Title', $extension->kit('Card:Title'));
        self::assertSame('Card', new EscapeRoomExtension()->kit('Card'));
        self::assertSame('Badge', new KitComponents()->name('Badge'));
        self::assertSame('rooms', $extension->domain());
        self::assertSame('tilttek_escape_room', new EscapeRoomExtension()->domain());
        self::assertSame('0:00', $extension->clock(-5));
        self::assertSame('0:59', $extension->clock(59));
        self::assertSame('42:07', $extension->clock(2_527));
        self::assertSame('59:59', $extension->clock(3_599));
        self::assertSame('1:00:00', $extension->clock(3_600));
        self::assertSame('1:02:07', $extension->clock(3_727));
    }
}
