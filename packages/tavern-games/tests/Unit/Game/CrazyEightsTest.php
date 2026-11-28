<?php

declare(strict_types=1);

namespace Tilttek\TavernGames\Tests\Unit\Game;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\CardDeck\CardId;
use Tilttek\CardDeck\Gesture\End;
use Tilttek\CardDeck\Gesture\Move as MoveCard;
use Tilttek\CardDeck\Gesture\Shuffle;
use Tilttek\CardDeck\ZoneId;
use Tilttek\GameEngine\Exception\IllegalMove;
use Tilttek\GameEngine\Exception\InvalidGame;
use Tilttek\GameEngine\GameState;
use Tilttek\GameEngine\Move;
use Tilttek\GameEngine\Outcome;
use Tilttek\GameEngine\Preferences;
use Tilttek\GameEngine\Randomness;
use Tilttek\GameEngine\Store\InMemoryLogStore;
use Tilttek\GameEngine\TableOptions;
use Tilttek\GameEngine\Tables;
use Tilttek\TavernGames\Contract\TavernGame;
use Tilttek\TavernGames\Game\CrazyEights;
use Tilttek\TavernGames\TavernGames;
use Tilttek\TavernGames\Tests\Unit\GameStates;

/**
 * The cards of the standard deck: `card-01` is AS, `card-14` AH, `card-27` AD, `card-40` AC, each suit from the ace
 * to the king.
 */
#[CoversClass(CrazyEights::class)]
final class CrazyEightsTest extends TestCase
{
    private const string SEED = 'abababababababababababababababababababababababababababababababab';

    private CrazyEights $game;

    protected function setUp(): void
    {
        $this->game = new CrazyEights();
    }

    public function testTheDefinition(): void
    {
        $definition = $this->game->definition();

        self::assertSame('crazy-eights', $definition->game);
        self::assertSame(2, $definition->minimumSeats);
        self::assertSame(6, $definition->maximumSeats);
        self::assertSame('hand', $definition->options[0]->name);
        self::assertSame('p1', CrazyEights::player(0)->value);
        self::assertSame('p3', CrazyEights::player(2)->value);
    }

    public function testEachSeatIsDealtAHandAndOneCardIsTurnedUp(): void
    {
        $state = GameStates::of($this->game, ['ann', 'bob', 'cid'], []);
        $data = $this->game->setup($state, new Randomness(self::SEED, 0));

        self::assertIsString($data['seed']);
        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/D', $data['seed']);
        self::assertIsArray($data['gestures']);
        self::assertCount(2, $data['gestures']);
        self::assertSame(['order' => ['ann', 'bob', 'cid'], 'current' => 0], $data['turns']);
        self::assertFalse($data['drawn']);
        self::assertSame(0, $data['passes']);
        self::assertSame($data, $this->game->setup($state, new Randomness(self::SEED, 0)));

        $view = $this->game->view(GameStates::of($this->game, ['ann', 'bob', 'cid'], $data), 'bob', Preferences::resolve([]));
        self::assertSame(['ann' => 5, 'bob' => 5, 'cid' => 5], $view['hands']);
        self::assertSame(36, $view['draw']);
        self::assertIsString($view['top']);
        self::assertSame(substr($view['top'], -1), $view['suit']);
        self::assertCount(5, $this->listOf($view['hand']));
        self::assertSame('8' === substr($view['top'], 0, -1) ? substr($view['top'], -1) : null, $data['suit']);
    }

    public function testTheCardTurnedUpIsTheFirstOfTheDrawPileAndLiesFaceUp(): void
    {
        $data = $this->game->setup(GameStates::of($this->game, ['ann', 'bob', 'cid'], []), new Randomness(self::SEED, 0));

        self::assertSame(['gesture' => 'move', 'card' => 'card-41', 'to' => 'discard', 'at' => 'top', 'face_up' => true], $this->listOf($data['gestures'])[1]);
        self::assertSame('2C', $this->view($data, 'bob', ['ann', 'bob', 'cid'])['top']);
    }

    public function testTheHandIsThreeToEightCards(): void
    {
        $options = $this->game->definition()->options;

        foreach ([3, 8] as $hand) {
            self::assertSame($hand, TableOptions::resolve($options, ['hand' => $hand])->int('hand'));
        }

        $refused = 0;

        foreach ([2, 9] as $hand) {
            try {
                TableOptions::resolve($options, ['hand' => $hand]);
            } catch (InvalidGame) {
                ++$refused;
            }
        }

        self::assertSame(2, $refused);
    }

    public function testTheHandOfTheTable(): void
    {
        $data = $this->game->setup(GameStates::of($this->game, ['ann', 'bob'], [], ['hand' => 8]), new Randomness(self::SEED, 0));

        self::assertSame(['ann' => 8, 'bob' => 8], $this->game->view(GameStates::of($this->game, ['ann', 'bob'], $data, ['hand' => 8]), null, Preferences::resolve([]))['hands']);
    }

    public function testAnEightTurnedUpNamesItsSuit(): void
    {
        for ($sequence = 0; $sequence < 500; ++$sequence) {
            $data = $this->game->setup(GameStates::of($this->game, ['ann', 'bob'], []), new Randomness(self::SEED, $sequence));
            $top = $this->game->view(GameStates::of($this->game, ['ann', 'bob'], $data), null, Preferences::resolve([]))['top'];
            self::assertIsString($top);

            if ('8' === substr($top, 0, -1)) {
                self::assertSame(substr($top, -1), $data['suit']);

                return;
            }

            self::assertNull($data['suit']);
        }

        self::fail('No eight was turned up.');
    }

    public function testTheMovesOfTheSeatPlaying(): void
    {
        $state = $this->state([['card-18'], ['card-29']], 'card-15');

        foreach ([CrazyEights::PLAY, CrazyEights::DRAW, CrazyEights::PASS] as $type) {
            self::assertTrue($this->game->allows($state, new Move($type, 'ann')));
            self::assertFalse($this->game->allows($state, new Move($type, 'bob')));
        }

        self::assertTrue($this->game->allows($state, new Move(TavernGame::TIMEOUT, null, ['seat' => 'ann'])));
        self::assertFalse($this->game->allows($state, new Move(TavernGame::TIMEOUT, null, ['seat' => 'bob'])));
        self::assertFalse($this->game->allows($state, new Move(TavernGame::TIMEOUT, 'ann', ['seat' => 'ann'])));
        self::assertFalse($this->game->allows($state, new Move('crazy-eights.peek', 'ann')));
    }

    public function testACardOfTheSameSuitIsPlayed(): void
    {
        $data = $this->play($this->state([['card-18', 'card-48'], ['card-29']], 'card-15'), 'ann', ['card' => 'card-18']);
        $view = $this->view($data, 'ann');

        self::assertSame('5H', $view['top']);
        self::assertSame('H', $view['suit']);
        self::assertSame([['card' => 'card-48', 'face' => '9C']], $view['hand']);
        self::assertSame('bob', $view['turn']);
        self::assertNull($data['suit']);
        self::assertFalse($data['drawn']);
        self::assertSame(0, $data['passes']);
    }

    public function testACardOfTheSameRankIsPlayed(): void
    {
        $view = $this->view($this->play($this->state([['card-48', 'card-18'], ['card-29']], 'card-09'), 'ann', ['card' => 'card-48']), 'bob');

        self::assertSame('9C', $view['top']);
        self::assertSame([['card' => 'card-29', 'face' => '3D']], $view['hand']);
    }

    public function testAnotherCardIsRefused(): void
    {
        $this->expectException(IllegalMove::class);
        $this->expectExceptionMessageIsOrContains('The card is an eight, or follows the suit H or the rank of the top card.');

        $this->play($this->state([['card-48', 'card-18'], ['card-29']], 'card-15'), 'ann', ['card' => 'card-48']);
    }

    public function testAnEightNamesTheSuitToFollow(): void
    {
        $data = $this->play($this->state([['card-08', 'card-48'], ['card-29', 'card-16']], 'card-15'), 'ann', ['card' => 'card-08', 'suit' => 'D']);

        self::assertSame('D', $data['suit']);
        self::assertSame('D', $this->view($data, 'bob')['suit']);
        self::assertSame('8S', $this->view($data, 'bob')['top']);

        $next = $this->play(GameStates::of($this->game, ['ann', 'bob'], $data), 'bob', ['card' => 'card-29']);
        self::assertNull($next['suit']);
    }

    public function testTheSuitNamedIsFollowedRatherThanTheTopCard(): void
    {
        $data = $this->play($this->state([['card-08', 'card-48'], ['card-29', 'card-16']], 'card-15'), 'ann', ['card' => 'card-08', 'suit' => 'D']);

        $this->expectException(IllegalMove::class);
        $this->expectExceptionMessageIsOrContains('follows the suit D');

        $this->play(GameStates::of($this->game, ['ann', 'bob'], $data), 'bob', ['card' => 'card-16']);
    }

    public function testAnEightFollowsAnything(): void
    {
        $data = $this->play($this->state([['card-47', 'card-48'], ['card-29']], 'card-15'), 'ann', ['card' => 'card-47', 'suit' => 'C']);

        self::assertSame('C', $data['suit']);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function refusedPlays(): iterable
    {
        yield 'an eight without a suit' => [['card' => 'card-08'], 'An eight names the suit to follow: {"suit": "S", "H", "D" or "C"}.'];
        yield 'an eight with another suit' => [['card' => 'card-08', 'suit' => 'X'], 'An eight names the suit to follow'];
        yield 'a card of another hand' => [['card' => 'card-29'], 'A seat plays a card of its hand: {"card": "card-07"}.'];
        yield 'a card of the discard pile' => [['card' => 'card-15'], 'A seat plays a card of its hand'];
        yield 'a card of the draw pile' => [['card' => 'card-50'], 'A seat plays a card of its hand'];
        yield 'a card the deck lacks' => [['card' => 'card-99'], 'A seat plays a card of its hand'];
        yield 'an identifier refused' => [['card' => 'not a card!'], 'A seat plays a card of its hand'];
        yield 'no card' => [[], 'A seat plays a card of its hand'];
    }

    /**
     * @param array<string, mixed> $payload
     */
    #[DataProvider('refusedPlays')]
    public function testARefusedPlay(array $payload, string $message): void
    {
        $this->expectException(IllegalMove::class);
        $this->expectExceptionMessageIsOrContains($message);

        $this->play($this->state([['card-08', 'card-48'], ['card-29']], 'card-15'), 'ann', $payload);
    }

    public function testACardIsDrawnOnceATurn(): void
    {
        $state = $this->state([['card-48'], ['card-29']], 'card-15');
        $data = $this->game->play($state, new Move(CrazyEights::DRAW, 'ann'), new Randomness(self::SEED, 1));
        $view = $this->view($data, 'ann');

        self::assertTrue($data['drawn']);
        self::assertTrue($view['drawn']);
        self::assertCount(2, $this->listOf($view['hand']));
        self::assertSame(48, $view['draw']);
        self::assertSame('ann', $view['turn']);

        $this->expectException(IllegalMove::class);
        $this->expectExceptionMessageIsOrContains('A seat draws one card a turn.');

        $this->game->play(GameStates::of($this->game, ['ann', 'bob'], $data), new Move(CrazyEights::DRAW, 'ann'), new Randomness(self::SEED, 2));
    }

    public function testACardDrawnIsPlayedOrTheSeatPasses(): void
    {
        $drawn = $this->game->play($this->state([['card-48'], ['card-29']], 'card-15'), new Move(CrazyEights::DRAW, 'ann'), new Randomness(self::SEED, 1));
        $passed = $this->game->play(GameStates::of($this->game, ['ann', 'bob'], $drawn), new Move(CrazyEights::PASS, 'ann'), new Randomness(self::SEED, 2));

        self::assertFalse($passed['drawn']);
        self::assertSame(0, $passed['passes']);
        self::assertSame('bob', $this->view($passed, 'ann')['turn']);
        self::assertCount(2, $this->listOf($this->view($passed, 'ann')['hand']));
    }

    public function testASeatDrawsBeforeItPasses(): void
    {
        $this->expectException(IllegalMove::class);
        $this->expectExceptionMessageIsOrContains('A seat draws a card before it passes, while a card is left to draw.');

        $this->game->play($this->state([['card-48'], ['card-29']], 'card-15'), new Move(CrazyEights::PASS, 'ann'), new Randomness(self::SEED, 1));
    }

    public function testTheTimeoutDrawsThenPasses(): void
    {
        $data = $this->game->play($this->state([['card-48'], ['card-29']], 'card-15', passes: 1), new Move(TavernGame::TIMEOUT, null, ['seat' => 'ann']), new Randomness(self::SEED, 1));
        $view = $this->view($data, 'ann');

        self::assertCount(2, $this->listOf($view['hand']));
        self::assertSame('bob', $view['turn']);
        self::assertFalse($data['drawn']);
        self::assertSame(0, $data['passes']);
    }

    public function testTheTimeoutAfterADrawPasses(): void
    {
        $state = $this->state([['card-48'], ['card-29']], 'card-15', drawn: true, passes: 1);
        $data = $this->game->play($state, new Move(TavernGame::TIMEOUT, null, ['seat' => 'ann']), new Randomness(self::SEED, 1));

        self::assertCount(1, $this->listOf($this->view($data, 'ann')['hand']));
        self::assertSame(0, $data['passes']);
        self::assertSame('bob', $this->view($data, 'ann')['turn']);
    }

    public function testTheDiscardPileIsShuffledBackWhenTheDrawPileIsEmpty(): void
    {
        $state = $this->exhausted(['card-01'], ['card-02'], array_map(static fn (int $card): string => \sprintf('card-%02d', $card), range(52, 3, -1)));
        $data = $this->game->play($state, new Move(CrazyEights::DRAW, 'ann'), new Randomness(self::SEED, 1));
        $view = $this->view($data, 'ann');

        self::assertCount(2, $this->listOf($view['hand']));
        self::assertSame('3S', $view['top']);
        self::assertSame(48, $view['draw']);
        self::assertTrue($data['drawn']);

        $gestures = $this->listOf($data['gestures']);
        $turnedUp = $this->listOf($gestures[\count($gestures) - 2]);

        self::assertSame(['gesture' => 'move', 'card' => 'card-03', 'to' => 'discard', 'at' => 'top', 'face_up' => true], $turnedUp);
    }

    public function testWithNothingToDrawTheSeatPasses(): void
    {
        $state = $this->exhausted(['card-01', 'card-02'], ['card-04'], ['card-03'], ['card-05', 'card-06']);

        try {
            $this->game->play($state, new Move(CrazyEights::DRAW, 'ann'), new Randomness(self::SEED, 1));
            self::fail('Nothing is left to draw.');
        } catch (IllegalMove $illegalMove) {
            self::assertSame('No card is left to draw: the seat passes.', $illegalMove->getMessage());
        }

        $passed = $this->game->play($state, new Move(CrazyEights::PASS, 'ann'), new Randomness(self::SEED, 1));
        self::assertSame(1, $passed['passes']);
        self::assertNull($this->game->outcome(GameStates::of($this->game, ['ann', 'bob', 'cid'], $passed)));

        $timedOut = $this->game->play(GameStates::of($this->game, ['ann', 'bob', 'cid'], $passed), new Move(TavernGame::TIMEOUT, null, ['seat' => 'bob']), new Randomness(self::SEED, 2));
        self::assertSame(2, $timedOut['passes']);
        self::assertSame('cid', $this->view($timedOut, 'ann', ['ann', 'bob', 'cid'])['turn']);

        $blocked = $this->game->play(GameStates::of($this->game, ['ann', 'bob', 'cid'], $timedOut), new Move(CrazyEights::PASS, 'cid'), new Randomness(self::SEED, 3));
        $outcome = $this->game->outcome(GameStates::of($this->game, ['ann', 'bob', 'cid'], $blocked));

        self::assertInstanceOf(Outcome::class, $outcome);
        self::assertSame(['bob'], $outcome->winners);
        self::assertSame('blocked', $outcome->reason);
        self::assertSame(2, $outcome->statistics->ofSeat('ann', 'cards'));
        self::assertSame(1, $outcome->statistics->ofSeat('bob', 'cards'));
    }

    public function testEqualHandsShareABlockedGame(): void
    {
        $state = $this->exhausted(['card-01'], ['card-04'], ['card-03'], ['card-05', 'card-06'], passes: 3);

        self::assertSame(['ann', 'bob'], $this->game->outcome($state)?->winners);
    }

    public function testAPlayAfterPassesStartsTheCountAgain(): void
    {
        $data = $this->play($this->state([['card-18', 'card-48'], ['card-29']], 'card-15', passes: 1), 'ann', ['card' => 'card-18']);

        self::assertSame(0, $data['passes']);
    }

    public function testTheFirstSeatToEmptyItsHandWins(): void
    {
        $data = $this->play($this->state([['card-18'], ['card-29', 'card-30']], 'card-15'), 'ann', ['card' => 'card-18']);
        $outcome = $this->game->outcome(GameStates::of($this->game, ['ann', 'bob'], $data));

        self::assertInstanceOf(Outcome::class, $outcome);
        self::assertSame(['ann'], $outcome->winners);
        self::assertSame('emptied-hand', $outcome->reason);
        self::assertSame(0, $outcome->statistics->ofSeat('ann', 'cards'));
        self::assertSame(2, $outcome->statistics->ofSeat('bob', 'cards'));
    }

    public function testAGameTooLongEndsOnTheFewestCards(): void
    {
        $state = $this->state([['card-18'], ['card-29', 'card-30']], 'card-15');
        $gestures = $state->data['gestures'];
        self::assertIsArray($gestures);
        $long = [...$gestures, ...array_fill(0, CrazyEights::MAX_GESTURES - \count($gestures) - 1, new Shuffle()->toArray())];
        $data = ['gestures' => $long] + $state->data;

        self::assertNull($this->game->outcome(GameStates::of($this->game, ['ann', 'bob'], $data)));

        $outcome = $this->game->outcome(GameStates::of($this->game, ['ann', 'bob'], ['gestures' => [...$long, new Shuffle()->toArray()]] + $state->data));
        self::assertSame(['ann'], $outcome?->winners);
        self::assertSame('too-long', $outcome->reason);
    }

    public function testTheViewHidesTheOthersCards(): void
    {
        $state = $this->state([['card-18', 'card-48'], ['card-29']], 'card-15');

        self::assertSame([
            'hand' => [['card' => 'card-29', 'face' => '3D']],
            'top' => '2H',
            'suit' => 'H',
            'draw' => 48,
            'hands' => ['ann' => 2, 'bob' => 1],
            'turn' => 'ann',
            'drawn' => false,
        ], $this->game->view($state, 'bob', Preferences::resolve([])));
        self::assertSame([], $this->game->view($state, null, Preferences::resolve([]))['hand']);
        self::assertSame('ann', $this->game->toAct($state));
        self::assertSame($state->data, $this->game->enter($state, new Randomness(self::SEED, 1)));
    }

    public function testNobodyActsOnceTheGameIsOver(): void
    {
        $state = $this->state([[], ['card-29']], 'card-15', outcome: Outcome::won(['ann'], 'emptied-hand'));

        self::assertNull($this->game->toAct($state));
        self::assertNull($this->game->view($state, 'ann', Preferences::resolve([]))['turn']);
    }

    public function testAWholeGameReplaysCardForCard(): void
    {
        $games = new TavernGames($this->game);
        $logs = new InMemoryLogStore();
        $tables = new Tables($games->games(), $logs);
        $state = $tables->open('crazy-eights', 't-1', ['ann', 'bob', 'cid'], [], str_repeat('cd', 32));

        for ($move = 0; $move < 400 && !$state->isOver(); ++$move) {
            $seat = $this->game->toAct($state);
            self::assertIsString($seat);
            $state = $tables->play('t-1', $this->choose($tables->view('t-1', $seat), $seat));
        }

        self::assertTrue($state->isOver());
        self::assertSame($state->data, new Tables($games->games(), $logs)->state('t-1')->data);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function corrupted(): iterable
    {
        $turns = ['order' => ['ann', 'bob'], 'current' => 0];

        yield 'a seed refused' => [['seed' => 'nope', 'gestures' => [], 'turns' => $turns, 'passes' => 0], 'The seed of the cards of Crazy Eights is not 64 hexadecimal digits.'];
        yield 'no seed' => [['gestures' => [], 'turns' => $turns, 'passes' => 0], 'The seed of the cards of Crazy Eights is not 64 hexadecimal digits.'];
        yield 'gestures that are a text' => [['seed' => self::SEED, 'gestures' => 'deal', 'turns' => $turns, 'passes' => 0], 'The gestures of Crazy Eights are a list.'];
        yield 'a gesture unknown' => [['seed' => self::SEED, 'gestures' => [['gesture' => 'juggle']], 'turns' => $turns, 'passes' => 0], 'The cards of Crazy Eights do not replay: '];
    }

    /**
     * @param array<string, mixed> $data
     */
    #[DataProvider('corrupted')]
    public function testCorruptedDataIsRefused(array $data, string $message): void
    {
        $this->expectException(InvalidGame::class);
        $this->expectExceptionMessageIsOrContains($message);

        $this->game->outcome(GameStates::of($this->game, ['ann', 'bob'], $data));
    }

    public function testAReplayRefusedKeepsTheReasonOfTheCards(): void
    {
        $this->expectException(InvalidGame::class);
        $this->expectExceptionMessageMatches('/^The cards of Crazy Eights do not replay: .+$/s');

        $this->game->outcome(GameStates::of($this->game, ['ann', 'bob'], ['seed' => self::SEED, 'gestures' => [['gesture' => 'juggle']], 'turns' => ['order' => ['ann', 'bob'], 'current' => 0], 'passes' => 0]));
    }

    public function testGesturesKeyedByTextAreReadAndWrittenAsAList(): void
    {
        $data = $this->state([['card-18', 'card-48'], ['card-29']], 'card-15')->data;
        $gestures = $this->listOf($data['gestures']);
        $data['gestures'] = array_combine(array_map(static fn (int $place): string => 'g'.$place, array_keys($gestures)), $gestures);

        $played = $this->play(GameStates::of($this->game, ['ann', 'bob'], $data), 'ann', ['card' => 'card-18']);

        self::assertTrue(array_is_list($this->listOf($played['gestures'])));
        self::assertSame('5H', $this->view($played, 'ann')['top']);
    }

    public function testPassesThatAreNotANumberAreRefused(): void
    {
        $this->expectException(InvalidGame::class);
        $this->expectExceptionMessageIsOrContains('The passes of Crazy Eights are a whole number.');

        $this->game->outcome(GameStates::of($this->game, ['ann', 'bob'], ['passes' => '0'] + $this->state([['card-18'], ['card-29']], 'card-15')->data));
    }

    /**
     * @return array<mixed>
     */
    private function listOf(mixed $value): array
    {
        self::assertIsArray($value);

        return $value;
    }

    /**
     * A state whose hands and top card are laid out by gestures, the other cards left in the draw pile.
     *
     * @param list<list<string>> $hands
     */
    private function state(array $hands, string $top, bool $drawn = false, int $passes = 0, ?Outcome $outcome = null): GameState
    {
        $seats = \array_slice(['ann', 'bob', 'cid'], 0, \count($hands));
        $gestures = [];

        foreach ($hands as $place => $cards) {
            foreach ($cards as $card) {
                $gestures[] = new MoveCard(new CardId($card), ZoneId::hand(CrazyEights::player($place)), End::Bottom)->toArray();
            }
        }

        $gestures[] = new MoveCard(new CardId($top), ZoneId::discard(), End::Top, true)->toArray();

        return GameStates::of($this->game, $seats, [
            'seed' => self::SEED,
            'gestures' => $gestures,
            'turns' => ['order' => $seats, 'current' => 0],
            'suit' => null,
            'drawn' => $drawn,
            'passes' => $passes,
        ], [], $outcome);
    }

    /**
     * A state of three seats whose draw pile is empty: two hands, then the discard pile from the bottom to the top, then
     * the hand of the third seat.
     *
     * @param list<string> $ann
     * @param list<string> $bob
     * @param list<string> $discard
     * @param list<string> $cid
     */
    private function exhausted(array $ann, array $bob, array $discard, array $cid = [], int $passes = 0): GameState
    {
        $state = $this->state([$ann, $bob, $cid], $discard[\count($discard) - 1], passes: $passes);
        $gestures = $state->data['gestures'];
        self::assertIsArray($gestures);
        $placed = [...$ann, ...$bob, ...$discard, ...$cid];

        foreach (range(1, 52) as $number) {
            $card = \sprintf('card-%02d', $number);

            if (!\in_array($card, $placed, true)) {
                $gestures[] = new MoveCard(new CardId($card), ZoneId::pile('out'))->toArray();
            }
        }

        foreach (\array_slice($discard, 0, -1) as $card) {
            $gestures[] = new MoveCard(new CardId($card), ZoneId::discard(), End::Bottom, true)->toArray();
        }

        return GameStates::of($this->game, ['ann', 'bob', 'cid'], ['gestures' => $gestures] + $state->data);
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function play(GameState $state, string $seat, array $payload): array
    {
        return $this->game->play($state, new Move(CrazyEights::PLAY, $seat, $payload), new Randomness(self::SEED, 1));
    }

    /**
     * @param array<string, mixed> $data
     * @param list<string>         $seats
     *
     * @return array<string, mixed>
     */
    private function view(array $data, string $seat, array $seats = ['ann', 'bob']): array
    {
        return $this->game->view(GameStates::of($this->game, $seats, $data), $seat, Preferences::resolve([]));
    }

    /**
     * A simple player: the first card it may play, else a draw, else a pass.
     *
     * @param array<string, mixed> $view
     */
    private function choose(array $view, string $seat): Move
    {
        $top = \is_string($view['top']) ? $view['top'] : '';
        $hand = \is_array($view['hand']) ? $view['hand'] : [];

        foreach ($hand as $card) {
            self::assertIsArray($card);
            $face = \is_string($card['face']) ? $card['face'] : '';

            if ('8' === substr($face, 0, -1) || substr($face, -1) === $view['suit'] || substr($face, 0, -1) === substr($top, 0, -1)) {
                return new Move(CrazyEights::PLAY, $seat, ['card' => $card['card'], 'suit' => 'H']);
            }
        }

        return true === $view['drawn'] || 0 === $view['draw'] ? new Move(CrazyEights::PASS, $seat) : new Move(CrazyEights::DRAW, $seat);
    }
}
