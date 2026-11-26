<?php

declare(strict_types=1);

namespace Tilttek\CardDeck\Tests\Unit\Gesture;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\CardDeck\CardId;
use Tilttek\CardDeck\Exception\InvalidDefinition;
use Tilttek\CardDeck\Gesture\Collect;
use Tilttek\CardDeck\Gesture\Deal;
use Tilttek\CardDeck\Gesture\Discard;
use Tilttek\CardDeck\Gesture\Draw;
use Tilttek\CardDeck\Gesture\End;
use Tilttek\CardDeck\Gesture\Flip;
use Tilttek\CardDeck\Gesture\Gesture;
use Tilttek\CardDeck\Gesture\GestureCodec;
use Tilttek\CardDeck\Gesture\Move;
use Tilttek\CardDeck\Gesture\Pick;
use Tilttek\CardDeck\Gesture\Shuffle;
use Tilttek\CardDeck\Gesture\TurnFace;
use Tilttek\CardDeck\PlayerId;
use Tilttek\CardDeck\ZoneId;

#[CoversClass(GestureCodec::class)]
#[CoversClass(Shuffle::class)]
#[CoversClass(Draw::class)]
#[CoversClass(Deal::class)]
#[CoversClass(Discard::class)]
#[CoversClass(Flip::class)]
#[CoversClass(TurnFace::class)]
#[CoversClass(Move::class)]
#[CoversClass(Collect::class)]
final class GestureCodecTest extends TestCase
{
    /**
     * @return iterable<string, array{Gesture, array<string, mixed>}>
     */
    public static function gestures(): iterable
    {
        $p1 = new PlayerId('p1');
        $p2 = new PlayerId('p2');

        yield 'shuffle' => [new Shuffle(), ['gesture' => 'shuffle', 'zone' => 'draw']];
        yield 'shuffle a pile' => [new Shuffle(ZoneId::pile('market')), ['gesture' => 'shuffle', 'zone' => 'pile:market']];
        yield 'draw' => [new Draw($p1), ['gesture' => 'draw', 'player' => 'p1', 'from' => 'top', 'zone' => 'draw', 'face_up' => false]];
        yield 'draw at random, face up' => [new Draw($p1, Pick::Random, ZoneId::hand($p2), true), ['gesture' => 'draw', 'player' => 'p1', 'from' => 'random', 'zone' => 'hand:p2', 'face_up' => true]];
        yield 'deal' => [new Deal([$p1, $p2], 5), ['gesture' => 'deal', 'players' => ['p1', 'p2'], 'count' => 5, 'face_up' => false, 'from' => 'draw']];
        yield 'deal face up from a pile' => [new Deal([$p2], 1, true, ZoneId::pile('x')), ['gesture' => 'deal', 'players' => ['p2'], 'count' => 1, 'face_up' => true, 'from' => 'pile:x']];
        yield 'discard' => [new Discard(new CardId('c1')), ['gesture' => 'discard', 'card' => 'c1']];
        yield 'flip' => [new Flip(new CardId('c1')), ['gesture' => 'flip', 'card' => 'c1']];
        yield 'turn face' => [new TurnFace(new CardId('c1'), 15), ['gesture' => 'turn_face', 'card' => 'c1', 'face' => 15]];
        yield 'move' => [new Move(new CardId('c1'), ZoneId::table()), ['gesture' => 'move', 'card' => 'c1', 'to' => 'table', 'at' => 'top', 'face_up' => false]];
        yield 'move to the bottom, face up' => [new Move(new CardId('c1'), ZoneId::discard(), End::Bottom, true), ['gesture' => 'move', 'card' => 'c1', 'to' => 'discard', 'at' => 'bottom', 'face_up' => true]];
        yield 'collect' => [new Collect(ZoneId::discard(), ZoneId::drawPile()), ['gesture' => 'collect', 'from' => 'discard', 'to' => 'draw', 'at' => 'top', 'face_up' => false]];
        yield 'collect to the bottom, face up' => [new Collect(ZoneId::table(), ZoneId::discard(), End::Bottom, true), ['gesture' => 'collect', 'from' => 'table', 'to' => 'discard', 'at' => 'bottom', 'face_up' => true]];
    }

    /**
     * @param array<string, mixed> $array
     */
    #[DataProvider('gestures')]
    public function testAGestureGoesToAnArrayAndBack(Gesture $gesture, array $array): void
    {
        self::assertSame($array, $gesture->toArray());
        self::assertEquals($gesture, GestureCodec::fromArray($array));
    }

    /**
     * @return iterable<string, array{mixed, string}>
     */
    public static function badGestures(): iterable
    {
        $field = static fn (string $name): string => \sprintf('The field "%s" of the gesture is missing or of the wrong type.', $name);

        yield 'not an array' => ['shuffle', 'A gesture is an array.'];
        yield 'no name' => [[], 'A gesture is shuffle, draw, deal, discard, flip, turn_face, move or collect.'];
        yield 'unknown name' => [['gesture' => 'cheat'], 'A gesture is shuffle, draw, deal, discard, flip, turn_face, move or collect.'];
        yield 'missing zone' => [['gesture' => 'shuffle'], $field('zone')];
        yield 'zone not a string' => [['gesture' => 'shuffle', 'zone' => 1], $field('zone')];
        yield 'unknown pick' => [['gesture' => 'draw', 'player' => 'p', 'from' => 'middle', 'zone' => 'draw', 'face_up' => false], $field('from')];
        yield 'missing face up' => [['gesture' => 'draw', 'player' => 'p', 'from' => 'top', 'zone' => 'draw'], $field('face_up')];
        yield 'face up not a boolean' => [['gesture' => 'draw', 'player' => 'p', 'from' => 'top', 'zone' => 'draw', 'face_up' => 1], $field('face_up')];
        yield 'missing players' => [['gesture' => 'deal', 'count' => 1, 'face_up' => false, 'from' => 'draw'], $field('players')];
        yield 'players not a list' => [['gesture' => 'deal', 'players' => ['a' => 'p'], 'count' => 1, 'face_up' => false, 'from' => 'draw'], $field('players')];
        yield 'player not a string' => [['gesture' => 'deal', 'players' => [1], 'count' => 1, 'face_up' => false, 'from' => 'draw'], $field('players')];
        yield 'count not an integer' => [['gesture' => 'deal', 'players' => ['p'], 'count' => '1', 'face_up' => false, 'from' => 'draw'], $field('count')];
        yield 'missing card' => [['gesture' => 'flip'], $field('card')];
        yield 'missing face' => [['gesture' => 'turn_face', 'card' => 'c'], $field('face')];
        yield 'unknown end' => [['gesture' => 'move', 'card' => 'c', 'to' => 'table', 'at' => 'middle', 'face_up' => false], $field('at')];
        yield 'missing end' => [['gesture' => 'collect', 'from' => 'table', 'to' => 'draw', 'face_up' => false], $field('at')];
    }

    #[DataProvider('badGestures')]
    public function testAGestureThatDoesNotDecodeIsRefused(mixed $data, string $message): void
    {
        $this->expectExceptionObject(InvalidDefinition::because($message));

        GestureCodec::fromArray($data);
    }

    public function testADealGoesToOneToSixtyFourPlayersEachOnce(): void
    {
        $players = array_map(static fn (int $index): PlayerId => new PlayerId('p'.$index), range(1, 64));
        self::assertCount(64, new Deal($players, 1)->players);
        self::assertSame(1024, new Deal($players, 1024)->count);

        foreach ([[], [...$players, new PlayerId('p65')], [new PlayerId('a'), new PlayerId('a')]] as $wrong) {
            try {
                new Deal($wrong, 1);
                self::fail('A deal to the wrong players was accepted.');
            } catch (InvalidDefinition $invalid) {
                self::assertSame('A deal goes to 1 to 64 players, each once.', $invalid->getMessage());
            }
        }

        foreach ([0, 1025] as $count) {
            try {
                new Deal([new PlayerId('a')], $count);
                self::fail('A deal out of bounds was accepted.');
            } catch (InvalidDefinition $invalid) {
                self::assertSame('A deal gives 1 to 1024 cards to each player.', $invalid->getMessage());
            }
        }
    }

    public function testAFaceIsNumberedZeroToFifteen(): void
    {
        self::assertSame(0, new TurnFace(new CardId('c'), 0)->face);

        foreach ([-1, 16] as $face) {
            try {
                new TurnFace(new CardId('c'), $face);
                self::fail('A face out of bounds was accepted.');
            } catch (InvalidDefinition $invalid) {
                self::assertSame('A face is numbered 0 to 15.', $invalid->getMessage());
            }
        }
    }

    public function testAZoneIsCollectedIntoAnotherOne(): void
    {
        $this->expectExceptionObject(InvalidDefinition::because('A zone is collected into another one.'));

        new Collect(ZoneId::table(), ZoneId::table());
    }
}
