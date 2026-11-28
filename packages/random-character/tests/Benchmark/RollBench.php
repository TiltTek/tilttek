<?php

declare(strict_types=1);

namespace Tilttek\RandomCharacter\Tests\Benchmark;

use Random\Engine\Mt19937;
use Random\Randomizer;
use Symfony\Component\Yaml\Yaml;
use Tilttek\Generator\Generator;
use Tilttek\Generator\Loading\Reader;
use Tilttek\RandomCharacter\CharacterRoller;
use Tilttek\RandomCharacter\CreationRules;
use Tilttek\RandomCharacter\Field;
use Tilttek\RandomCharacter\Format\RulesReader;
use Tilttek\RandomCharacter\Format\Syntax;
use Tilttek\RandomCharacter\StatMethod;
use Tilttek\RandomCharacter\Stats;
use Tilttek\RpgSystems\Attribute;
use Tilttek\RpgSystems\GameSystem;
use Tilttek\RpgSystems\Licence;
use Tilttek\RpgSystems\Provenance;
use Tilttek\RpgSystems\Systems;
use Tilttek\RpgSystems\Translations;

/**
 * What a "roll me a character" button does: six attributes of 4d6 drop the lowest and five fields, two of them drawn
 * from a generator named by an earlier field; and reading 100 rules from YAML when a cache is rebuilt.
 */
final readonly class RollBench
{
    private CharacterRoller $roller;

    private CreationRules $rules;

    private string $yaml;

    public function __construct()
    {
        $source = new Provenance('Bench', Licence::Owned);
        $keys = ['str', 'dex', 'con', 'int', 'wis', 'cha'];
        $systems = new Systems(new GameSystem('bench', new Translations(['en' => 'Bench']), $source, attributes: array_map(
            static fn (string $key): Attribute => new Attribute($key, new Translations(['en' => $key]), $source, minimum: 3, maximum: 18),
            $keys,
        )));
        $lists = ['kin' => ['elf', 'dwarf', 'human'], 'trait' => array_map(static fn (int $i): string => 'trait '.$i, range(1, 50))];
        $generators = ['kin' => ['templates' => "{{ pick('kin') }}"], 'trait' => ['templates' => "{{ pick('trait') }}"]];

        foreach (['elf', 'dwarf', 'human'] as $kin) {
            $lists['name.'.$kin] = array_map(static fn (int $i): string => $kin.' '.$i, range(1, 100));
            $generators['name.'.$kin] = ['templates' => "{{ pick('name.".$kin."') }}"];
        }

        $generator = new Generator(new Reader()->catalog(['bench' => ['lists' => $lists, 'generators' => $generators]]));
        $this->roller = new CharacterRoller($systems, $generator, randomizer: new Randomizer(new Mt19937(1)));
        $this->rules = new CreationRules('bench.hero', 'bench', new Stats(StatMethod::RolledArray, $keys, '4d6kh3'), [
            Field::generated('kin', 'kin'),
            Field::generated('name', 'name.{kin}'),
            Field::generated('traits', 'trait', 3, true),
            Field::rolled('age', '2d10+14'),
            Field::rolled('gold', '3d6*10'),
        ]);
        $rules = array_map(static fn (int $i): array => [
            'key' => 'r'.$i,
            'stats' => ['method' => 'rolled-array', 'roll' => '4d6kh3', 'attributes' => $keys],
            'fields' => [['key' => 'kin', 'generator' => 'kin'], ['key' => 'name', 'generator' => 'name.{kin}'], ['key' => 'age', 'roll' => '2d10+14']],
        ], range(1, 100));
        $this->yaml = Yaml::dump(['format' => RulesReader::FORMAT, 'system' => 'bench', 'rules' => $rules], 6, 2);
    }

    public function benchRollACharacter(): int
    {
        return \count($this->roller->roll($this->rules)->fields);
    }

    public function benchReplayACharacter(): int
    {
        return \count($this->roller->roll($this->rules, 'table 7')->stats);
    }

    /**
     * @Revs(10)
     *
     * @Iterations(3)
     */
    public function benchReadYaml(): int
    {
        return \count(new RulesReader()->parse($this->yaml, Syntax::Yaml));
    }
}
