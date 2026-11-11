<?php

declare(strict_types=1);

namespace Tilttek\Dice\Custom;

use Tilttek\Dice\Exception\CannotRoll;
use Tilttek\Dice\Exception\InvalidDefinition;

/**
 * The custom dice the application declares, by name. The library ships none: a publisher's dice are its site's data.
 */
final readonly class CustomDice
{
    /**
     * @var array<string, DieDefinition>
     */
    private array $dice;

    public function __construct(DieDefinition ...$dice)
    {
        $byName = [];

        foreach ($dice as $die) {
            if (\array_key_exists($die->name, $byName)) {
                throw InvalidDefinition::because(\sprintf('Two dice are named "%s".', $die->name));
            }

            $byName[$die->name] = $die;
        }

        $this->dice = $byName;
    }

    /**
     * @throws CannotRoll when no die has this name
     */
    public function get(string $name): DieDefinition
    {
        return $this->dice[$name] ?? throw CannotRoll::unknownDie($name);
    }

    public function has(string $name): bool
    {
        return \array_key_exists($name, $this->dice);
    }

    /**
     * @return list<DieDefinition>
     */
    public function all(): array
    {
        return array_values($this->dice);
    }
}
