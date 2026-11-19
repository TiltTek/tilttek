<?php

declare(strict_types=1);

namespace Tilttek\EscapeRoomBundle\Tests\Unit\Application;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\EscapeRoom\Game;
use Tilttek\EscapeRoomBundle\Application\ConfiguredRoomCatalog;
use Tilttek\EscapeRoomBundle\Application\EscapeRoomTopics;
use Tilttek\EscapeRoomBundle\Application\NoEscapeRoomAccess;
use Tilttek\EscapeRoomBundle\Application\StoredGame;
use Tilttek\EscapeRoomBundle\Application\UnnamedTeams;
use Tilttek\EscapeRoomBundle\Tests\Unit\Fixture\Rooms;
use Tilttek\PuzzleBuilder\Progress;

#[CoversClass(ConfiguredRoomCatalog::class)]
#[CoversClass(EscapeRoomTopics::class)]
#[CoversClass(NoEscapeRoomAccess::class)]
#[CoversClass(UnnamedTeams::class)]
#[CoversClass(StoredGame::class)]
final class ApplicationTest extends TestCase
{
    public function testTheRoomsOfTheConfigurationAreReadWhole(): void
    {
        $catalog = new ConfiguredRoomCatalog(Rooms::config());
        $crypt = $catalog->room('crypt');

        self::assertSame(['crypt', 'cell'], $catalog->ids());
        self::assertNotNull($crypt);
        self::assertSame(['crypt', 'The crypt', 3_600, 120, 300, 2, 6], [$crypt->id, $crypt->title, $crypt->duration, $crypt->hintPenalty, $crypt->skipPenalty, $crypt->minPlayers, $crypt->maxPlayers]);
        self::assertSame(['candle', 'crest', 'tomb'], $crypt->scenario->order());
        self::assertNull($catalog->room('vault'));
        self::assertSame([], new ConfiguredRoomCatalog()->ids());
    }

    public function testTheTopicOfAGameNamesItsPrefixAndIdentifier(): void
    {
        self::assertSame('tilttek-escape-room/games/g-1', new EscapeRoomTopics()->topic('g-1'));
        self::assertSame('rooms/games/a%20b', new EscapeRoomTopics('rooms')->topic('a b'));
    }

    public function testWithoutAccessNobodyRunsWatchesOrSpeaksForAGame(): void
    {
        $room = new ConfiguredRoomCatalog(Rooms::config())->room('cell');
        self::assertNotNull($room);
        $game = new StoredGame('g-1', $room, new Game('cell', ResourceReference::of('team', 'owls'), null, new DateTimeImmutable(), Progress::start()));
        $access = new NoEscapeRoomAccess();

        self::assertNull($access->host($game));
        self::assertFalse($access->watches($game));
        self::assertFalse($access->speaksForTeam($game));
        self::assertNull(new UnnamedTeams()->name(ResourceReference::of('team', 'owls')));
    }
}
