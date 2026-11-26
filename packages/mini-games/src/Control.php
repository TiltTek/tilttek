<?php

declare(strict_types=1);

namespace Tilttek\MiniGames;

use Tilttek\MiniGames\Exception\InvalidMiniGame;

/**
 * One action of a game and the keys that perform it, so that every game is played with the keyboard alone: the keys
 * are values of `KeyboardEvent.key` — `ArrowUp`, `Enter`, `7` —, the action a name the application translates.
 */
final readonly class Control
{
    /**
     * @param string       $action the action, `higher`, in lowercase words joined by `-`
     * @param list<string> $keys   1 to 12 keys, each 1 to 32 printable ASCII characters
     *
     * @throws InvalidMiniGame
     */
    public function __construct(
        public string $action,
        public array $keys,
    ) {
        if (1 !== preg_match('/^[a-z][a-z0-9]*(?:-[a-z0-9]+)*$/D', $action) || \strlen($action) > 64) {
            throw InvalidMiniGame::because(\sprintf('The action "%s" is 1 to 64 lowercase letters and digits, words joined by "-".', $action));
        }

        if ([] === $keys || \count($keys) > 12) {
            throw InvalidMiniGame::because(\sprintf('The action "%s" has 1 to 12 keys.', $action));
        }

        foreach ($keys as $key) {
            if (1 !== preg_match('/^[\x21-\x7E]{1,32}$/D', $key)) {
                throw InvalidMiniGame::because(\sprintf('A key of the action "%s" is 1 to 32 printable ASCII characters.', $action));
            }
        }
    }
}
