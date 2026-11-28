<?php

declare(strict_types=1);

namespace Tilttek\Encounter;

use Tilttek\Encounter\Contract\DifficultyRules;
use Tilttek\Encounter\Difficulty\Declaration;
use Tilttek\Encounter\Exception\InvalidEncounter;
use Tilttek\Encounter\Exception\UnknownSystem;
use Tilttek\RpgSystems\GameSystem;
use Tilttek\RpgSystems\Systems;

/**
 * The rules of difficulty of each game system an application offers, found by the system's key or by the
 * `tilttek/rpg-systems` system itself.
 */
final readonly class Rulebooks
{
    /** The most game systems. */
    public const int MAX_SYSTEMS = 1000;

    /** @var array<string, DifficultyRules> by the key of the system */
    private array $rules;

    /**
     * @throws InvalidEncounter when two rules are for one system, or there are too many
     */
    public function __construct(DifficultyRules ...$rules)
    {
        if (\count($rules) > self::MAX_SYSTEMS) {
            throw InvalidEncounter::because(\sprintf('Rules of difficulty are declared for at most %d game systems.', self::MAX_SYSTEMS));
        }

        $bySystem = [];
        foreach ($rules as $rule) {
            if (isset($bySystem[$rule->system()])) {
                throw InvalidEncounter::because(\sprintf('Two rules of difficulty are declared for the game system %s.', $rule->system()));
            }

            $bySystem[$rule->system()] = $rule;
        }

        $this->rules = $bySystem;
    }

    /**
     * The rules each system declares by its difficulty numbers `encounter`, and the rules the application brings for
     * the systems without them or in their place: a rule given for a system replaces the one its numbers declare. A
     * system without difficulty numbers of the key has no rules unless the application gives them.
     *
     * @param iterable<GameSystem>|Systems $systems
     * @param string                       $record  the key of the difficulty numbers of an encounter
     *
     * @throws InvalidEncounter when a system's numbers are not rules of difficulty, two given rules are for one system,
     *                          or there are too many
     */
    public static function fromSystems(iterable|Systems $systems, string $record = Declaration::RECORD, DifficultyRules ...$rules): self
    {
        $given = [];
        foreach ($rules as $rule) {
            if (isset($given[$rule->system()])) {
                throw InvalidEncounter::because(\sprintf('Two rules of difficulty are declared for the game system %s.', $rule->system()));
            }

            $given[$rule->system()] = $rule;
        }

        $bySystem = [];
        foreach ($systems instanceof Systems ? $systems->all() : $systems as $system) {
            if (!isset($given[$system->key]) && $system->hasDifficulty($record)) {
                $bySystem[$system->key] = Declaration::fromSystem($system, $record);
            }
        }

        return new self(...array_values([...$bySystem, ...$given]));
    }

    /**
     * @throws UnknownSystem when no rules are declared for the system
     */
    public function for(GameSystem|string $system): DifficultyRules
    {
        $key = $system instanceof GameSystem ? $system->key : $system;

        return $this->rules[$key] ?? throw UnknownSystem::of($key);
    }

    public function has(GameSystem|string $system): bool
    {
        return isset($this->rules[$system instanceof GameSystem ? $system->key : $system]);
    }
}
