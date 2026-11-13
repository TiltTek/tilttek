<?php

declare(strict_types=1);

namespace Tilttek\GameEngine;

use Tilttek\GameEngine\Exception\InvalidGame;

/**
 * The preferences of one player at a table — how their cards are sorted, whether moves are confirmed, a colour for the
 * blind —: values a game offers as options of a seat, which change what the seat's view shows and never the rules. They
 * are not part of the log: two players with other preferences see the same state, and a replay ignores them.
 */
final readonly class Preferences
{
    /**
     * @param array<string, bool|int|string> $values
     */
    private function __construct(public array $values)
    {
    }

    /**
     * The values a player chose, checked by Symfony's OptionsResolver against the preferences the game offers, and the
     * defaults of the others: a preference the game does not offer, or a value it does not allow, is refused.
     *
     * @param list<Option>         $preferences the preferences the game offers
     * @param array<string, mixed> $values      the values the player chose
     *
     * @throws InvalidGame
     */
    public static function resolve(array $preferences, array $values = []): self
    {
        return new self(OptionValues::resolve($preferences, $values, 'the preferences of the seat'));
    }

    /**
     * @throws InvalidGame when the game offers no such preference
     */
    public function get(string $name): bool|int|string
    {
        return $this->values[$name] ?? throw InvalidGame::because(\sprintf('The game offers no preference "%s".', $name));
    }

    public function bool(string $name): bool
    {
        return true === $this->get($name);
    }

    public function int(string $name): int
    {
        $value = $this->get($name);

        return \is_int($value) ? $value : throw InvalidGame::because(\sprintf('The preference "%s" is not a whole number.', $name));
    }

    public function string(string $name): string
    {
        $value = $this->get($name);

        return \is_string($value) ? $value : throw InvalidGame::because(\sprintf('The preference "%s" is not a choice.', $name));
    }
}
