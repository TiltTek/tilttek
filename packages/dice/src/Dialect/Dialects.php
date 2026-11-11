<?php

declare(strict_types=1);

namespace Tilttek\Dice\Dialect;

use Tilttek\Dice\Exception\InvalidDefinition;

/**
 * The dialects by name: Roll20, Foundry VTT, Avrae and Rolisteam's DiceParser built in, and those the application adds or replaces.
 */
final readonly class Dialects
{
    /**
     * @var array<string, Dialect>
     */
    private array $dialects;

    public function __construct(Dialect ...$dialects)
    {
        $byName = [];

        foreach ([new Roll20(), new FoundryVtt(), new Avrae(), new RolisteamDiceParser(), ...$dialects] as $dialect) {
            $byName[$dialect->name()] = $dialect;
        }

        $this->dialects = $byName;
    }

    /**
     * @throws InvalidDefinition when no dialect has this name
     */
    public function get(string $name): Dialect
    {
        return $this->dialects[$name] ?? throw InvalidDefinition::because(\sprintf('No dialect is named "%s"; the dialects are %s.', $name, implode(', ', $this->names())));
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_keys($this->dialects);
    }
}
