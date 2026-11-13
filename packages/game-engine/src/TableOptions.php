<?php

declare(strict_types=1);

namespace Tilttek\GameEngine;

use Tilttek\GameEngine\Exception\InvalidGame;

/**
 * The options of one table: the values its creator chose, checked against the options the game offers, and the
 * defaults of the others.
 */
final readonly class TableOptions
{
    /**
     * @param array<string, bool|int|string> $values
     */
    private function __construct(public array $values)
    {
    }

    /**
     * The values given, checked by Symfony's OptionsResolver: an option the game does not offer, or a value it does not
     * allow, is refused.
     *
     * @param list<Option>         $options the options the game offers
     * @param array<string, mixed> $values  the values chosen for the table
     *
     * @throws InvalidGame
     */
    public static function resolve(array $options, array $values = []): self
    {
        return new self(OptionValues::resolve($options, $values, 'the options of the table'));
    }

    /**
     * The options of a table read back as `$values` holds them — from a cache —, without the game's definition: each
     * value a boolean, a whole number or a text under a name.
     *
     * @param array<mixed> $values
     *
     * @throws InvalidGame
     */
    public static function read(array $values): self
    {
        return new self(OptionValues::read($values, 'the options of the table'));
    }

    /**
     * @throws InvalidGame when the game offers no such option
     */
    public function get(string $name): bool|int|string
    {
        return $this->values[$name] ?? throw InvalidGame::because(\sprintf('The game offers no option "%s".', $name));
    }

    public function bool(string $name): bool
    {
        return true === $this->get($name);
    }

    public function int(string $name): int
    {
        $value = $this->get($name);

        return \is_int($value) ? $value : throw InvalidGame::because(\sprintf('The option "%s" is not a whole number.', $name));
    }

    public function string(string $name): string
    {
        $value = $this->get($name);

        return \is_string($value) ? $value : throw InvalidGame::because(\sprintf('The option "%s" is not a choice.', $name));
    }
}
