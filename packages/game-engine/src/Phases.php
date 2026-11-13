<?php

declare(strict_types=1);

namespace Tilttek\GameEngine;

use Symfony\Component\Workflow\Definition;
use Symfony\Component\Workflow\Dumper\MermaidDumper;
use Symfony\Component\Workflow\Exception\InvalidDefinitionException;
use Symfony\Component\Workflow\Exception\LogicException;
use Symfony\Component\Workflow\MarkingStore\MethodMarkingStore;
use Symfony\Component\Workflow\StateMachine;
use Symfony\Component\Workflow\Transition as WorkflowTransition;
use Symfony\Component\Workflow\Validator\StateMachineValidator;
use Tilttek\GameEngine\Exception\IllegalMove;
use Tilttek\GameEngine\Exception\InvalidGame;

/**
 * The phases of a game as a state machine of Symfony Workflow: the definition checked by its validator, the
 * transitions it allows from a phase, and the diagram its Mermaid dumper draws. No event dispatcher is given: a
 * transition depends only on the phase it leaves, so that replaying a log takes the same transitions.
 */
final readonly class Phases
{
    private Definition $definition;

    private StateMachine $machine;

    /**
     * @param list<Phase>      $phases
     * @param list<Transition> $transitions
     *
     * @throws InvalidGame
     */
    public function __construct(string $game, array $phases, array $transitions, string $initial)
    {
        $names = array_map(static fn (Phase $phase): string => $phase->name, $phases);

        foreach ($transitions as $transition) {
            foreach ([...$transition->from, $transition->to] as $phase) {
                if (!\in_array($phase, $names, true)) {
                    throw InvalidGame::because(\sprintf('The transition "%s" of the game "%s" names the phase "%s", which the game does not have.', $transition->name, $game, $phase));
                }
            }
        }

        if (!\in_array($initial, $names, true)) {
            throw InvalidGame::because(\sprintf('The game "%s" starts in the phase "%s", which it does not have.', $game, $initial));
        }

        $this->definition = new Definition(
            $names,
            array_merge(...array_map(
                static fn (Transition $transition): array => array_map(static fn (string $from): WorkflowTransition => new WorkflowTransition($transition->name, $from, $transition->to), $transition->from),
                $transitions,
            )),
            $initial,
        );

        try {
            new StateMachineValidator()->validate($this->definition, $game);
        } catch (InvalidDefinitionException $invalidDefinitionException) {
            throw InvalidGame::because(\sprintf('The phases of the game "%s" do not make a state machine: %s', $game, $invalidDefinitionException->getMessage()), $invalidDefinitionException);
        }

        $this->machine = new StateMachine($this->definition, new MethodMarkingStore(true, 'phase'), null, $game);
    }

    /**
     * Whether a transition may leave a phase.
     */
    public function can(string $phase, string $transition): bool
    {
        return $this->machine->can(new PhaseCursor($phase), $transition);
    }

    /**
     * The phase a transition leads to from a phase.
     *
     * @throws IllegalMove when the transition may not leave that phase
     */
    public function apply(string $phase, string $transition): string
    {
        $cursor = new PhaseCursor($phase);

        try {
            $this->machine->apply($cursor, $transition);
        } catch (LogicException) {
            throw IllegalMove::because(\sprintf('The transition "%s" cannot leave the phase "%s".', $transition, $phase));
        }

        return $cursor->getPhase();
    }

    /**
     * The transitions that may leave a phase, in the order of the definition.
     *
     * @return list<string>
     */
    public function from(string $phase): array
    {
        return array_values(array_unique(array_map(static fn (WorkflowTransition $transition): string => $transition->getName(), $this->machine->getEnabledTransitions(new PhaseCursor($phase)))));
    }

    /**
     * The phases and transitions as a Mermaid flowchart, for the documentation of a game.
     */
    public function mermaid(?string $phase = null): string
    {
        $marking = null === $phase ? null : $this->machine->getMarking(new PhaseCursor($phase));

        return new MermaidDumper(MermaidDumper::TRANSITION_TYPE_STATEMACHINE)->dump($this->definition, $marking);
    }
}
