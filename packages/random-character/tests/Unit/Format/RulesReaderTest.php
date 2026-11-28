<?php

declare(strict_types=1);

namespace Tilttek\RandomCharacter\Tests\Unit\Format;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\Generator\Generator;
use Tilttek\Generator\Loading\FileLoader;
use Tilttek\RandomCharacter\CharacterRoller;
use Tilttek\RandomCharacter\Exception\InvalidDocument;
use Tilttek\RandomCharacter\Exception\InvalidRules;
use Tilttek\RandomCharacter\Field;
use Tilttek\RandomCharacter\FieldKind;
use Tilttek\RandomCharacter\Format\RulesReader;
use Tilttek\RandomCharacter\Format\Syntax;
use Tilttek\RandomCharacter\Internal\Fields;
use Tilttek\RandomCharacter\StatMethod;
use Tilttek\RpgSystems\Format\SystemReader;

#[CoversClass(RulesReader::class)]
#[CoversClass(Syntax::class)]
#[CoversClass(Fields::class)]
#[CoversClass(InvalidDocument::class)]
final class RulesReaderTest extends TestCase
{
    private const string EXAMPLES = __DIR__.'/../../../examples';

    public function testReadsTheExample(): void
    {
        $catalogue = new RulesReader()->load(self::EXAMPLES.'/lanterns.yaml');

        self::assertSame(['lanterns.wanderer', 'lanterns.lamplighter'], array_keys($catalogue->all()));
        $wanderer = $catalogue->get('lanterns.wanderer');
        self::assertSame('lanterns', $wanderer->system);
        self::assertSame(StatMethod::RolledArray, $wanderer->stats?->method);
        self::assertSame('1d4+1', $wanderer->stats->roll);
        self::assertSame(['grit', 'wits', 'glow'], $wanderer->stats->attributes);
        self::assertSame(['lineage', 'name', 'traits', 'age'], array_map(static fn (Field $field): string => $field->key, $wanderer->fields));
        self::assertSame('lanterns.name.{lineage}', $wanderer->field('name')->generator);
        self::assertSame(2, $wanderer->field('traits')->count);
        self::assertTrue($wanderer->field('traits')->unique);
        self::assertFalse($wanderer->field('name')->unique);
        self::assertSame(FieldKind::Rolled, $wanderer->field('age')->kind);
        self::assertSame('2d10+14', $wanderer->field('age')->roll);
        self::assertSame([4, 3, 2], $catalogue->get('lanterns.lamplighter')->stats?->values);
    }

    public function testTheExampleRolls(): void
    {
        $systems = new SystemReader()->load(self::EXAMPLES.'/lanterns-system.yaml');
        $generator = new Generator(new FileLoader()->catalog([self::EXAMPLES.'/lanterns-tables.yaml']));
        $roller = new CharacterRoller($systems, $generator);

        foreach (new RulesReader()->load(self::EXAMPLES.'/lanterns.yaml')->all() as $rules) {
            self::assertSame([], $roller->check($rules));
            $character = $roller->roll($rules, 'example');
            self::assertSame(['grit', 'wits', 'glow'], array_keys($character->stats));
            self::assertSame($character->toArray(), $roller->roll($rules, 'example')->toArray());
        }
    }

    public function testJsonReadsAsYaml(): void
    {
        $reader = new RulesReader();
        $document = $reader->decode((string) file_get_contents(self::EXAMPLES.'/lanterns.yaml'), Syntax::Yaml);
        $json = json_encode($document, \JSON_THROW_ON_ERROR);

        self::assertEquals($reader->catalogue($document), $reader->parse($json, Syntax::Json));
    }

    public function testARuleNamesItsOwnSystem(): void
    {
        $catalogue = new RulesReader()->parse(<<<'YAML'
            format: tilttek-random-character/1
            system: lanterns
            rules:
              - { key: a, system: other, fields: [{ key: age, roll: 1d6 }] }
              - { key: b, fields: [{ key: name, generator: names, count: 3 }] }
            YAML, Syntax::Yaml);

        self::assertSame('other', $catalogue->get('a')->system);
        self::assertSame('lanterns', $catalogue->get('b')->system);
        self::assertSame(3, $catalogue->get('b')->field('name')->count);
        self::assertNull($catalogue->get('b')->stats);
    }

    public function testTheSyntaxOfAFile(): void
    {
        self::assertSame(Syntax::Yaml, Syntax::ofPath('a/rules.yaml'));
        self::assertSame(Syntax::Yaml, Syntax::ofPath('rules.YML'));
        self::assertSame(Syntax::Json, Syntax::ofPath('rules.Json'));

        $this->expectExceptionObject(InvalidDocument::at('', 'the file "rules.txt" is neither .yaml, .yml nor .json.'));
        Syntax::ofPath('a/rules.txt');
    }

    public function testRefusesAMissingFile(): void
    {
        $this->expectExceptionObject(InvalidDocument::at('', 'the file "missing.yaml" cannot be read.'));

        new RulesReader()->load(self::EXAMPLES.'/missing.yaml');
    }

    public function testRefusesADirectory(): void
    {
        $this->expectExceptionObject(InvalidDocument::at('', 'the file "examples.yaml" cannot be read.'));

        $directory = sys_get_temp_dir().'/random-character-'.bin2hex(random_bytes(4)).'/examples.yaml';
        mkdir($directory, 0o700, true);

        try {
            new RulesReader()->load($directory);
        } finally {
            rmdir($directory);
            rmdir(\dirname($directory));
        }
    }

    public function testRefusesAFileTooLong(): void
    {
        $size = (int) filesize(self::EXAMPLES.'/lanterns.yaml');

        self::assertCount(2, new RulesReader($size)->load(self::EXAMPLES.'/lanterns.yaml'));

        $this->expectExceptionObject(InvalidDocument::at('', \sprintf('the file "lanterns.yaml" is longer than %d bytes.', $size - 1)));
        new RulesReader($size - 1)->load(self::EXAMPLES.'/lanterns.yaml');
    }

    public function testRefusesATextTooLong(): void
    {
        $text = 'format: tilttek-random-character/1';

        self::assertSame(['format' => 'tilttek-random-character/1'], new RulesReader(\strlen($text))->decode($text, Syntax::Yaml));
        self::assertSame(1_048_576, RulesReader::MAX_BYTES);
        self::assertSame([[[]]], new RulesReader()->decode('[[[]]]', Syntax::Json));
        self::assertCount(1, new RulesReader()->decode(str_repeat('[', 63).str_repeat(']', 63), Syntax::Json));

        $this->expectExceptionObject(InvalidDocument::at('', \sprintf('the document is longer than %d bytes.', \strlen($text) - 1)));
        new RulesReader(\strlen($text) - 1)->decode($text, Syntax::Yaml);
    }

    public function testADocumentTooLongByDefaultIsRefused(): void
    {
        $this->expectExceptionObject(InvalidDocument::at('', 'the document is longer than 1048576 bytes.'));

        new RulesReader()->decode(str_repeat(' ', RulesReader::MAX_BYTES + 1), Syntax::Yaml);
    }

    /**
     * @return iterable<string, array{string, Syntax, non-empty-string}>
     */
    public static function undecodable(): iterable
    {
        yield 'wrong YAML' => ['a: [', Syntax::Yaml, 'The document is not valid YAML: '];
        yield 'wrong JSON' => ['{', Syntax::Json, 'The document is not valid JSON: '];
        yield 'a scalar' => ['12', Syntax::Yaml, 'The document is not a mapping.'];
        yield 'deep JSON' => [str_repeat('[', 64).str_repeat(']', 64), Syntax::Json, 'The document is not valid JSON: '];
        yield 'a YAML object' => ['a: !php/object O:8:"stdClass":0:{}', Syntax::Yaml, 'The document is not valid YAML: '];
    }

    /**
     * @param non-empty-string $message
     */
    #[DataProvider('undecodable')]
    public function testRefusesUndecodableTexts(string $text, Syntax $syntax, string $message): void
    {
        try {
            new RulesReader()->parse($text, $syntax);
            self::fail('The text is refused.');
        } catch (InvalidDocument $invalidDocument) {
            self::assertStringStartsWith($message, $invalidDocument->getMessage());
        }
    }

    /**
     * @return iterable<string, array{array<mixed>, non-empty-string}>
     */
    public static function refused(): iterable
    {
        $rule = ['key' => 'a', 'system' => 's', 'fields' => [['key' => 'age', 'roll' => '1d6']]];
        $document = static fn (array $rules): array => ['format' => RulesReader::FORMAT, 'rules' => $rules];

        yield 'no format' => [['rules' => [$rule]], 'format: the document must name the format "tilttek-random-character/1".'];
        yield 'another format' => [['format' => 'other/1', 'rules' => [$rule]], 'format: the document must name the format "tilttek-random-character/1".'];
        yield 'an unknown key' => [['format' => RulesReader::FORMAT, 'rules' => [$rule], 'extra' => 1], '"extra" is not one of format, system, rules.'];
        yield 'a list document' => [[1, 2], 'A mapping of names to values is expected.'];
        yield 'no rules' => [['format' => RulesReader::FORMAT], 'rules: a list of 1 to 1000 items is expected.'];
        yield 'empty rules' => [$document([]), 'rules: a list of 1 to 1000 items is expected.'];
        yield 'rules as a map' => [$document(['a' => $rule]), 'rules: a list of 1 to 1000 items is expected.'];
        yield 'a wrong system' => [['format' => RulesReader::FORMAT, 'system' => 3, 'rules' => [$rule]], 'system: a text is expected.'];
        yield 'a rule not a map' => [$document(['a']), 'rules[0]: a mapping of names to values is expected.'];
        yield 'a rule without system' => [$document([['key' => 'a', 'fields' => [['key' => 'age', 'roll' => '1d6']]]]), 'rules[0].system: the rules name no system, and the document none for them.'];
        yield 'a rule without key' => [$document([['system' => 's', 'fields' => [['key' => 'age', 'roll' => '1d6']]]]), 'rules[0].key: a text is expected.'];
        yield 'a rule with a wrong key' => [$document([['key' => 'A', 'system' => 's', 'fields' => [['key' => 'age', 'roll' => '1d6']]]]), 'rules[0]: the key of creation rules "A" must be'];
        yield 'a rule without anything' => [$document([['key' => 'a', 'system' => 's']]), 'rules[0]: the creation rules "a" have stats, fields, or both.'];
        yield 'an unknown rule key' => [$document([[...$rule, 'name' => 'x']]), 'rules[0]: "name" is not one of key, system, stats, fields.'];
        yield 'empty fields' => [$document([['key' => 'a', 'system' => 's', 'fields' => []]]), 'rules[0].fields: a list of 1 to 64 items is expected.'];
        yield 'two rules with one key' => [$document([$rule, $rule]), 'rules: two creation rules have the key "a".'];
        yield 'a field not a map' => [$document([['key' => 'a', 'system' => 's', 'fields' => ['x']]]), 'rules[0].fields[0]: a mapping of names to values is expected.'];
        yield 'a field without key' => [$document([['key' => 'a', 'system' => 's', 'fields' => [['roll' => '1d6']]]]), 'rules[0].fields[0].key: a text is expected.'];
        yield 'a field with both' => [$document([['key' => 'a', 'system' => 's', 'fields' => [['key' => 'x', 'roll' => '1d6', 'generator' => 'g']]]]), 'rules[0].fields[0]: a field has either a generator or a roll.'];
        yield 'a field with neither' => [$document([['key' => 'a', 'system' => 's', 'fields' => [['key' => 'x']]]]), 'rules[0].fields[0]: a field has either a generator or a roll.'];
        yield 'a rolled field unique' => [$document([['key' => 'a', 'system' => 's', 'fields' => [['key' => 'x', 'roll' => '1d6', 'unique' => true]]]]), 'rules[0].fields[0].unique: a rolled field has no "unique".'];
        yield 'a wrong count' => [$document([['key' => 'a', 'system' => 's', 'fields' => [['key' => 'x', 'roll' => '1d6', 'count' => '2']]]]), 'rules[0].fields[0].count: a whole number is expected.'];
        yield 'a count too high' => [$document([['key' => 'a', 'system' => 's', 'fields' => [['key' => 'x', 'roll' => '1d6', 'count' => 17]]]]), 'rules[0].fields[0]: the field "x" gives 1 to 16 values.'];
        yield 'a wrong unique' => [$document([['key' => 'a', 'system' => 's', 'fields' => [['key' => 'x', 'generator' => 'g', 'unique' => 'yes']]]]), 'rules[0].fields[0].unique: true or false is expected.'];
        yield 'a wrong roll' => [$document([['key' => 'a', 'system' => 's', 'fields' => [['key' => 'x', 'roll' => 6]]]]), 'rules[0].fields[0].roll: a text is expected.'];
        yield 'a wrong generator' => [$document([['key' => 'a', 'system' => 's', 'fields' => [['key' => 'x', 'generator' => 'a b']]]]), 'rules[0].fields[0]: the field "x" names the generator "a b"'];
        yield 'an unknown field key' => [$document([['key' => 'a', 'system' => 's', 'fields' => [['key' => 'x', 'roll' => '1d6', 'weight' => 2]]]]), 'rules[0].fields[0]: "weight" is not one of key, generator, roll, count, unique.'];
        yield 'a forward reference' => [$document([['key' => 'a', 'system' => 's', 'fields' => [['key' => 'x', 'generator' => 'n.{y}'], ['key' => 'y', 'roll' => '1d6']]]]), 'rules[0]: the field "x" names its generator by the field "y", which is not before it.'];
        yield 'stats not a map' => [$document([['key' => 'a', 'system' => 's', 'stats' => 'x']]), 'rules[0].stats: a mapping of names to values is expected.'];
        yield 'stats without method' => [$document([['key' => 'a', 'system' => 's', 'stats' => ['attributes' => ['str'], 'roll' => '3d6']]]), 'rules[0].stats.method: a text is expected.'];
        yield 'an unknown method' => [$document([['key' => 'a', 'system' => 's', 'stats' => ['method' => 'point-buy', 'attributes' => ['str']]]]), 'rules[0].stats.method: the method is one of each, rolled-array, array.'];
        yield 'stats without attributes' => [$document([['key' => 'a', 'system' => 's', 'stats' => ['method' => 'each', 'roll' => '3d6']]]), 'rules[0].stats.attributes: a list of 1 to 32 items is expected.'];
        yield 'an attribute not a text' => [$document([['key' => 'a', 'system' => 's', 'stats' => ['method' => 'each', 'roll' => '3d6', 'attributes' => ['str', 4]]]]), 'rules[0].stats.attributes[1]: a text is expected.'];
        yield 'a value not a number' => [$document([['key' => 'a', 'system' => 's', 'stats' => ['method' => 'array', 'attributes' => ['str', 'dex'], 'values' => [3, '4']]]]), 'rules[0].stats.values[1]: a whole number is expected.'];
        yield 'empty values' => [$document([['key' => 'a', 'system' => 's', 'stats' => ['method' => 'array', 'attributes' => ['str'], 'values' => []]]]), 'rules[0].stats.values: a list of 1 to 32 items is expected.'];
        yield 'a wrong stat roll' => [$document([['key' => 'a', 'system' => 's', 'stats' => ['method' => 'each', 'attributes' => ['str'], 'roll' => 3]]]), 'rules[0].stats.roll: a text is expected.'];
        yield 'stats refused' => [$document([['key' => 'a', 'system' => 's', 'stats' => ['method' => 'array', 'attributes' => ['str'], 'roll' => '3d6', 'values' => [3]]]]), 'rules[0].stats: the stats of a fixed array have no roll, and one value per attribute.'];
        yield 'an unknown stats key' => [$document([['key' => 'a', 'system' => 's', 'stats' => ['method' => 'each', 'attributes' => ['str'], 'roll' => '3d6', 'bonus' => 1]]]), 'rules[0].stats: "bonus" is not one of method, attributes, roll, values.'];
    }

    /**
     * @param array<mixed>     $document
     * @param non-empty-string $message
     */
    #[DataProvider('refused')]
    public function testRefusesWrongDocuments(array $document, string $message): void
    {
        try {
            new RulesReader()->catalogue($document);
            self::fail('The document is refused.');
        } catch (InvalidDocument $invalidDocument) {
            self::assertStringStartsWith($message, $invalidDocument->getMessage());
        }
    }

    public function testARefusalKeepsTheRulesException(): void
    {
        try {
            new RulesReader()->catalogue(['format' => RulesReader::FORMAT, 'rules' => [['key' => 'A', 'system' => 's', 'fields' => [['key' => 'x', 'roll' => '1d6']]]]]);
            self::fail('The document is refused.');
        } catch (InvalidDocument $invalidDocument) {
            self::assertInstanceOf(InvalidRules::class, $invalidDocument->getPrevious());
        }
    }
}
