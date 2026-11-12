<?php

declare(strict_types=1);

namespace Tilttek\Generator\Definition;

use Tilttek\Generator\Exception\InvalidDefinition;
use Tilttek\Generator\Exception\UnknownGenerator;
use Tilttek\Generator\Exception\UnknownList;

/**
 * The lists and the generators of an application, by name.
 */
final readonly class Catalog
{
    /**
     * @var array<string, EntryList>
     */
    private array $lists;

    /**
     * @var array<string, GeneratorDefinition>
     */
    private array $generators;

    /**
     * @param list<EntryList>           $lists
     * @param list<GeneratorDefinition> $generators
     */
    public function __construct(array $lists = [], array $generators = [])
    {
        $byName = [];

        foreach ($lists as $list) {
            if (isset($byName[$list->name])) {
                throw InvalidDefinition::at($list->name, 'two lists have this name');
            }

            $byName[$list->name] = $list;
        }

        $this->lists = $byName;
        $byName = [];

        foreach ($generators as $generator) {
            if (isset($byName[$generator->name])) {
                throw InvalidDefinition::at($generator->name, 'two generators have this name');
            }

            $byName[$generator->name] = $generator;
        }

        $this->generators = $byName;
    }

    public function hasList(string $name): bool
    {
        return isset($this->lists[$name]);
    }

    public function list(string $name): EntryList
    {
        return $this->lists[$name] ?? throw UnknownList::named($name);
    }

    public function hasGenerator(string $name): bool
    {
        return isset($this->generators[$name]);
    }

    public function generator(string $name): GeneratorDefinition
    {
        return $this->generators[$name] ?? throw UnknownGenerator::named($name);
    }

    /**
     * @return array<string, EntryList>
     */
    public function lists(): array
    {
        return $this->lists;
    }

    /**
     * @return array<string, GeneratorDefinition>
     */
    public function generators(): array
    {
        return $this->generators;
    }
}
