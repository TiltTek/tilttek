<?php

declare(strict_types=1);

namespace Tilttek\GameEngine;

use Tilttek\GameEngine\Exception\InvalidGame;

/**
 * A change of phase the game allows: its name, the phases it leaves from, and the phase it leads to.
 */
final readonly class Transition
{
    /**
     * @param string       $name the transition's name, `start-encounter`
     * @param list<string> $from the phases it may leave from
     * @param string       $to   the phase it leads to
     *
     * @throws InvalidGame
     */
    public function __construct(
        public string $name,
        public array $from,
        public string $to,
    ) {
        Name::check($name, 'a transition');

        if ([] === $from) {
            throw InvalidGame::because(\sprintf('The transition "%s" leaves from no phase.', $name));
        }
    }
}
