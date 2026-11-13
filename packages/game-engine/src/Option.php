<?php

declare(strict_types=1);

namespace Tilttek\GameEngine;

use Tilttek\GameEngine\Exception\InvalidGame;

/**
 * An option a game offers its tables — a variant, a target score, a timer's length —, with its kind, its default and
 * the values it allows.
 */
final readonly class Option
{
    /**
     * @param list<string> $choices the values a choice allows
     *
     * @throws InvalidGame
     */
    private function __construct(
        public string $name,
        public OptionKind $kind,
        public bool|int|string $default,
        public ?int $minimum = null,
        public ?int $maximum = null,
        public array $choices = [],
    ) {
        Name::check($name, 'an option');
    }

    /**
     * @throws InvalidGame
     */
    public static function boolean(string $name, bool $default): self
    {
        return new self($name, OptionKind::Boolean, $default);
    }

    /**
     * @throws InvalidGame when the default lies outside the bounds
     */
    public static function integer(string $name, int $default, int $minimum, int $maximum): self
    {
        if ($default < $minimum || $default > $maximum) {
            throw InvalidGame::because(\sprintf('The default of the option "%s", %d, lies outside %d to %d.', $name, $default, $minimum, $maximum));
        }

        return new self($name, OptionKind::Integer, $default, $minimum, $maximum);
    }

    /**
     * @param list<string> $choices
     *
     * @throws InvalidGame when the default is not one of the choices
     */
    public static function choice(string $name, string $default, array $choices): self
    {
        if (!\in_array($default, $choices, true)) {
            throw InvalidGame::because(\sprintf('The default of the option "%s", "%s", is not one of %s.', $name, $default, implode(', ', $choices)));
        }

        return new self($name, OptionKind::Choice, $default, choices: $choices);
    }

    /**
     * Whether a value is one this option allows.
     */
    public function allows(mixed $value): bool
    {
        return match ($this->kind) {
            OptionKind::Boolean => \is_bool($value),
            OptionKind::Integer => \is_int($value) && $value >= $this->minimum && $value <= $this->maximum,
            OptionKind::Choice => \in_array($value, $this->choices, true),
        };
    }
}
