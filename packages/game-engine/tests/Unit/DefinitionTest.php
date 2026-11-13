<?php

declare(strict_types=1);

namespace Tilttek\GameEngine\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\GameEngine\Exception\IllegalMove;
use Tilttek\GameEngine\Exception\InvalidGame;
use Tilttek\GameEngine\GameDefinition;
use Tilttek\GameEngine\Name;
use Tilttek\GameEngine\Option;
use Tilttek\GameEngine\OptionKind;
use Tilttek\GameEngine\Phase;
use Tilttek\GameEngine\PhaseCursor;
use Tilttek\GameEngine\Phases;
use Tilttek\GameEngine\TableOptions;
use Tilttek\GameEngine\Transition;
use Tilttek\GameEngine\TurnOrder;

#[CoversClass(GameDefinition::class)]
#[CoversClass(Phases::class)]
#[CoversClass(PhaseCursor::class)]
#[CoversClass(Phase::class)]
#[CoversClass(Transition::class)]
#[CoversClass(Option::class)]
#[CoversClass(TableOptions::class)]
#[CoversClass(Name::class)]
#[CoversClass(InvalidGame::class)]
final class DefinitionTest extends TestCase
{
    public function testADefinitionStartsInItsFirstPhaseOrTheOneItNames(): void
    {
        $phases = [new Phase('setup'), new Phase('play', TurnOrder::Sequential, 2)];
        $definition = new GameDefinition('cards', $phases, [new Transition('deal', ['setup'], 'play')]);

        self::assertSame('setup', $definition->initial);
        self::assertSame($phases[0], $definition->initialPhase());
        self::assertSame($phases[1], $definition->phase('play'));
        self::assertSame([1, 8, [], [], false, '1'], [$definition->minimumSeats, $definition->maximumSeats, $definition->options, $definition->outOfTurn, $definition->undo, $definition->version]);
        self::assertSame('play', new GameDefinition('cards', $phases, [], 'play')->initialPhase()->name);
        self::assertSame(2, $phases[1]->movesPerTurn);
        self::assertSame(TurnOrder::Free, $phases[0]->turnOrder);
        self::assertSame('play', $definition->machine->apply('setup', 'deal'));
        self::assertSame([], $definition->machine->from('play'));
        self::assertSame(64, new GameDefinition('crowd', $phases, minimumSeats: 64, maximumSeats: 64)->maximumSeats);

        try {
            $definition->phase('end');
            self::fail('An unknown phase was found.');
        } catch (InvalidGame $invalidGame) {
            self::assertSame('The game "cards" has no phase "end".', $invalidGame->getMessage());
        }

        $this->expectException(IllegalMove::class);
        $this->expectExceptionMessageIsOrContains('The transition "deal" cannot leave the phase "play".');

        $definition->machine->apply('play', 'deal');
    }

    public function testABrokenDefinitionIsRefused(): void
    {
        $play = [new Phase('play')];

        foreach ([
            [static fn (): GameDefinition => new GameDefinition('Cards', $play), 'The name of a game "Cards" must be 1 to 64'],
            [static fn (): GameDefinition => new GameDefinition('cards', []), 'The game "cards" has no phase.'],
            [static fn (): GameDefinition => new GameDefinition('cards', $play, minimumSeats: 0), 'The game "cards" seats 0 to 8 players; it seats 1 to 64.'],
            [static fn (): GameDefinition => new GameDefinition('cards', $play, minimumSeats: 3, maximumSeats: 2), 'The game "cards" seats 3 to 2 players'],
            [static fn (): GameDefinition => new GameDefinition('cards', $play, maximumSeats: 65), 'The game "cards" seats 1 to 65 players'],
            [static fn (): GameDefinition => new GameDefinition('cards', [new Phase('play'), new Phase('play')]), 'The game "cards" has two of the phase "play".'],
            [static fn (): GameDefinition => new GameDefinition('cards', $play, options: [Option::boolean('fast', true), Option::boolean('fast', false)]), 'The game "cards" has two of the option "fast".'],
            [static fn (): GameDefinition => new GameDefinition('cards', $play, outOfTurn: ['engine.end-turn']), 'The game "cards" cannot declare the engine\'s move "engine.end-turn".'],
            [static fn (): GameDefinition => new GameDefinition('cards', $play, outOfTurn: ['Chat']), 'The move type "Chat" must be'],
            [static fn (): GameDefinition => new GameDefinition('cards', $play, version: ''), 'The version of the game "cards" is 1 to 64 characters.'],
            [static fn (): GameDefinition => new GameDefinition('cards', $play, version: str_repeat('1', 65)), 'The version of the game "cards" is 1 to 64 characters.'],
            [static fn (): GameDefinition => new GameDefinition('cards', $play, [new Transition('go', ['play'], 'end')]), 'The transition "go" of the game "cards" names the phase "end", which the game does not have.'],
            [static fn (): GameDefinition => new GameDefinition('cards', $play, [new Transition('go', ['start'], 'play')]), 'The transition "go" of the game "cards" names the phase "start"'],
            [static fn (): GameDefinition => new GameDefinition('cards', $play, [], 'end'), 'The game "cards" starts in the phase "end", which it does not have.'],
            [static fn (): GameDefinition => new GameDefinition('cards', [new Phase('a'), new Phase('b'), new Phase('c')], [new Transition('go', ['a'], 'b'), new Transition('go', ['a'], 'c')]), 'The phases of the game "cards" do not make a state machine: '],
            [static fn (): Transition => new Transition('go', [], 'play'), 'The transition "go" leaves from no phase.'],
            [static fn (): Phase => new Phase('play', TurnOrder::Sequential, 0), 'The phase "play" ends a turn after 1 to 100 moves, not 0.'],
            [static fn (): Phase => new Phase('play', TurnOrder::Sequential, 101), 'The phase "play" ends a turn after 1 to 100 moves, not 101.'],
            [static fn (): Phase => new Phase('play', TurnOrder::Free, 1), 'The phase "play" has no turns: it cannot end one after 1 moves.'],
            [static fn (): Phase => new Phase(str_repeat('a', 65)), 'The name of a phase'],
            [static fn (): Phase => new Phase('two--dashes'), 'The name of a phase "two--dashes"'],
        ] as [$make, $message]) {
            try {
                $make();
                self::fail(\sprintf('"%s" was accepted.', $message));
            } catch (InvalidGame $exception) {
                self::assertStringStartsWith($message, $exception->getMessage());
            }
        }

        self::assertSame(64, \strlen(new Phase(str_repeat('a', 64))->name));
        self::assertSame(100, new Phase('play', TurnOrder::Sequential, 100)->movesPerTurn);
        self::assertSame('b_2', new Phase('b_2')->name);
    }

    public function testOptionsAreCheckedByTheResolver(): void
    {
        $options = [Option::boolean('fast', false), Option::integer('target', 20, 10, 100), Option::choice('track', 'short', ['short', 'long'])];
        $defaults = TableOptions::resolve($options);

        self::assertSame(['fast' => false, 'target' => 20, 'track' => 'short'], $defaults->values);
        $chosen = TableOptions::resolve($options, ['fast' => true, 'target' => 100, 'track' => 'long']);
        self::assertSame([true, 100, 'long', true], [$chosen->bool('fast'), $chosen->int('target'), $chosen->string('track'), $chosen->get('fast')]);
        self::assertSame(10, TableOptions::resolve($options, ['target' => 10])->int('target'));
        self::assertSame([OptionKind::Boolean, OptionKind::Integer, OptionKind::Choice], array_map(static fn (Option $option): OptionKind => $option->kind, $options));
        self::assertSame([10, 100, []], [$options[1]->minimum, $options[1]->maximum, $options[1]->choices]);

        foreach ([
            ['speed' => 1],
            ['fast' => 'yes'],
            ['target' => 9],
            ['target' => 101],
            ['target' => '20'],
            ['track' => 'medium'],
        ] as $values) {
            try {
                TableOptions::resolve($options, $values);
                self::fail(\sprintf('The options %s were accepted.', var_export($values, true)));
            } catch (InvalidGame $exception) {
                self::assertStringStartsWith('The options of the table are refused: ', $exception->getMessage());
            }
        }

        foreach ([
            [static fn (): mixed => $defaults->get('speed'), 'The game offers no option "speed".'],
            [static fn (): mixed => $defaults->int('fast'), 'The option "fast" is not a whole number.'],
            [static fn (): mixed => $defaults->string('target'), 'The option "target" is not a choice.'],
            [static fn (): mixed => Option::integer('target', 9, 10, 100), 'The default of the option "target", 9, lies outside 10 to 100.'],
            [static fn (): mixed => Option::integer('target', 101, 10, 100), 'The default of the option "target", 101, lies outside 10 to 100.'],
            [static fn (): mixed => Option::choice('track', 'medium', ['short', 'long']), 'The default of the option "track", "medium", is not one of short, long.'],
            [static fn (): mixed => Option::boolean('Fast', true), 'The name of an option "Fast"'],
        ] as [$make, $message]) {
            try {
                $make();
                self::fail(\sprintf('"%s" was accepted.', $message));
            } catch (InvalidGame $exception) {
                self::assertStringStartsWith($message, $exception->getMessage());
            }
        }

        self::assertFalse($defaults->bool('target'));
        self::assertSame(10, Option::integer('target', 10, 10, 100)->default);
        self::assertSame(100, Option::integer('target', 100, 10, 100)->default);
    }

    public function testNamesTypesAndIdentifiersHaveTheirShape(): void
    {
        self::assertSame('race.roll-twice', Name::type('race.roll-twice'));
        self::assertSame(str_repeat('a', 64), Name::type(str_repeat('a', 64)));
        self::assertSame('member:7.b_c-d', Name::identifier('member:7.b_c-d', 'a seat'));
        self::assertSame(str_repeat('a', 128), Name::identifier(str_repeat('a', 128), 'a seat'));

        foreach (['', 'Race', 'race.', '.race', 'race..roll', 'race.2x', str_repeat('a', 65), "race\n"] as $type) {
            try {
                Name::type($type);
                self::fail(\sprintf('The type "%s" was accepted.', $type));
            } catch (InvalidGame $exception) {
                self::assertStringStartsWith('The move type', $exception->getMessage());
            }
        }

        foreach (['', '7', ':a', 'a b', str_repeat('a', 129), "a\n"] as $identifier) {
            try {
                Name::identifier($identifier, 'a seat');
                self::fail(\sprintf('The identifier "%s" was accepted.', $identifier));
            } catch (InvalidGame $exception) {
                self::assertStringStartsWith('The identifier of a seat', $exception->getMessage());
            }
        }
    }
}
