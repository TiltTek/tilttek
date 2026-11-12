<?php

declare(strict_types=1);

namespace Tilttek\Generator\Tests\Unit\Engine;

use FilesystemIterator;
use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tilttek\Generator\Definition\Body;
use Tilttek\Generator\Definition\Catalog;
use Tilttek\Generator\Definition\GeneratorDefinition;
use Tilttek\Generator\Engine\Problem;
use Tilttek\Generator\Engine\Producer;
use Tilttek\Generator\Engine\ReferenceCollector;
use Tilttek\Generator\Engine\SandboxPolicy;
use Tilttek\Generator\Exception\InvalidCatalog;
use Tilttek\Generator\Exception\InvalidDefinition;
use Tilttek\Generator\Generator;
use Tilttek\Generator\Tests\Unit\Fixture\Catalogs;

#[CoversClass(Producer::class)]
#[CoversClass(ReferenceCollector::class)]
#[CoversClass(SandboxPolicy::class)]
#[CoversClass(Generator::class)]
#[CoversClass(InvalidCatalog::class)]
#[CoversClass(Problem::class)]
final class CheckTest extends TestCase
{
    private const array LISTS = ['l' => [['text' => 'a', 'tags' => ['kind' => 'x']], 'b'], 'n' => [['text' => 'c', 'tags' => ['1' => 'x']]]];

    /**
     * @return iterable<string, array{array<string, mixed>, list<non-empty-string>}>
     */
    public static function problems(): iterable
    {
        yield 'syntax' => [['g' => ['templates' => '{{ pick( }}']], ['g:template:0: Unclosed "(".']];
        yield 'unknown filter' => [['g' => ['templates' => "{{ 'x'|nope }}"]], ['g:template:0: Unknown "nope" filter.']];
        yield 'tag outside the sandbox' => [['g' => ['templates' => '{% macro m() %}{% endmacro %}']], ['g:template:0: Tag "macro" is not allowed.']];
        yield 'filter outside the sandbox' => [['g' => ['templates' => "{{ 'x'|nl2br }}"]], ['g:template:0: Filter "nl2br" is not allowed.']];
        yield 'function outside the sandbox' => [['g' => ['templates' => '{{ random() }}']], ['g:template:0: Function "random" is not allowed.']];
        yield 'test outside the sandbox' => [['g' => ['templates' => '{{ 1 is constant("PHP_EOL") ? 1 }}']], ['g:template:0: Test "constant" is not allowed.']];
        yield 'range' => [['g' => ['templates' => '{% for i in 1..9 %}{% endfor %}']], ['g:template:0: the range operator ".." is not allowed']];
        yield 'unknown list' => [['g' => ['templates' => "{{ pick('nope') }}{{ pick('l') }}"]], ['g:template:0: the list "nope" does not exist']];
        yield 'unknown tag' => [['g' => ['templates' => "{{ pick('l', where: {kind: 1, era: 2, (x): 3}) }}"]], ['g:template:0: no entry of the list "l" has the tag "era"']];
        yield 'unknown positional tag' => [['g' => ['templates' => "{{ pick('l', 1, {era: 2}) }}"]], ['g:template:0: no entry of the list "l" has the tag "era"']];
        yield 'unknown generator called' => [['g' => ['templates' => "{{ generate('nope') }}"]], ['g:template:0: the generator "nope" does not exist']];
        yield 'names given by variables are checked at the draw' => [['g' => ['templates' => "{% set n = 'x' %}{{ pick(n) }}{{ generate(n) }}{{ pick(1) }}{{ pick('l', where: w|default({})) }}"]], []];
        yield 'unknown generated field' => [['g' => ['fields' => ['staff' => ['generate' => 'nope']]]], ['g:staff: the generator "nope" does not exist']];
        yield 'unknown generated cell' => [['g' => ['map' => ['layout' => 'square', 'width' => 1, 'height' => 1, 'cell' => ['generate' => 'nope']]]], ['g:cell: the generator "nope" does not exist']];
        yield 'unknown generated node' => [['g' => ['map' => ['layout' => 'graph', 'nodes' => 2, 'cell' => ['generate' => 'nope']]]], ['g:cell: the generator "nope" does not exist']];
        yield 'nodes that do not roll' => [['g' => ['map' => ['layout' => 'graph', 'nodes' => '2x', 'cell' => 'x']]], ['g: the number of nodes "2x" does not roll: ']];
        yield 'unknown language' => [['g' => ['templates' => 'x', 'locale' => 'tlh']], ['g: no grammar is known for the language "tlh"']];
        yield 'bad filter name' => [['g' => ['templates' => 'x', 'filters' => ['upper', 'upper %}{{ x']]], ['g: "upper %}{{ x" is not a filter name']];
        yield 'filter name with a capital' => [['g' => ['templates' => 'x', 'filters' => ['Upper']]], ['g: "Upper" is not a filter name']];
        yield 'filter name with a prefix' => [['g' => ['templates' => 'x', 'filters' => ['1upper']]], ['g: "1upper" is not a filter name']];
        yield 'filter name with a suffix' => [['g' => ['templates' => 'x', 'filters' => ['upper!']]], ['g: "upper!" is not a filter name']];
        yield 'filter outside the sandbox after the template' => [['g' => ['templates' => 'x', 'filters' => ['nl2br']]], ['g:template:0: Filter "nl2br" is not allowed.']];
        yield 'problems everywhere' => [
            ['g' => ['vars' => ['v' => '{{ x('], 'fields' => ['f' => "{{ pick('nope') }}"]], 'm' => ['map' => ['layout' => 'hex', 'width' => 1, 'height' => 1, 'cell' => "{{ generate('nope') }}"]]],
            ['g:var:v: ', 'g:field:f: the list "nope" does not exist', 'm:cell: the generator "nope" does not exist'],
        ];
        yield 'two problems of a definition' => [['g' => ['templates' => 'x', 'locale' => 'tlh', 'filters' => ['a!']]], ['g: no grammar is known for the language "tlh"', 'g: "a!" is not a filter name']];
        yield 'two problems of a template' => [['g' => ['templates' => "{{ generate('nope') }}{{ pick('none') }}{{ pick('nil') }}{{ pick('l', where: {era: 1}) }}"]], ['g:template:0: the generator "nope" does not exist', 'g:template:0: the list "none" does not exist', 'g:template:0: the list "nil" does not exist', 'g:template:0: no entry of the list "l" has the tag "era"']];
        yield 'a numeric tag' => [['g' => ['templates' => "{{ pick('n', where: {1: 'x'}) }}"]], []];
        yield 'everything allowed' => [['g' => ['templates' => implode('', [
            "{% set a = pick('l') %}{% if a is defined and a is not empty and a is not null %}{% endif %}",
            '{% for e in [1, 2] %}{{ e is even ? 1 }}{{ e is odd ? 1 }}{{ e is divisible by(2) ? 1 }}{{ e is same as(2) ? 1 }}{% endfor %}',
            "{% apply upper %}x{% endapply %}{{ 'x'|capitalize|title|lower|trim|replace({'x': 'y'})|slice(0, 1)|default('z')|upper_first }}",
            "{{ [1]|join|length }}{{ [1]|first }}{{ [1]|last }}{{ -1|abs|round }}{{ 'x'|elide|article|agree|plural }}{{ roll('1d6') }}{{ [] is iterable ? 1 }}{{ x is none ? 1 }}",
        ])]], []];
    }

    /**
     * @param array<string, mixed>   $generators
     * @param list<non-empty-string> $expected
     */
    #[DataProvider('problems')]
    public function testACatalogIsCheckedWhenItIsBuilt(array $generators, array $expected): void
    {
        try {
            Catalogs::generator(self::LISTS, $generators);
        } catch (InvalidCatalog $invalidCatalog) {
            $found = array_map(static fn (Problem $problem): string => (string) $problem, $invalidCatalog->problems());
            self::assertCount(\count($expected), $found, implode("\n", $found));

            foreach ($expected as $index => $problem) {
                self::assertStringStartsWith($problem, $found[$index]);
            }

            self::assertSame(implode("\n", $found), $invalidCatalog->getMessage());

            return;
        }

        self::assertSame([], $expected);
    }

    public function testTheDefaultLanguageMustHaveAGrammar(): void
    {
        $this->expectExceptionObject(InvalidDefinition::at('tlh', 'no grammar is known for this language'));

        new Generator(new Catalog(), locale: 'tlh');
    }

    public function testABodyOfAnUnknownKindCannotBeDrawn(): void
    {
        $generator = new Generator(new Catalog([], [new GeneratorDefinition('g', new class implements Body {})]));

        $this->expectExceptionObject(new LogicException('The generator "g" has a body of an unknown kind.'));

        $generator->draw('g');
    }

    public function testAProblemSaysWhereItIs(): void
    {
        $problem = new Problem('g:template:0', 'wrong');

        self::assertSame('g:template:0: wrong', (string) $problem);
        self::assertSame(['g:template:0', 'wrong'], [$problem->location, $problem->message]);
    }

    public function testEveryTemplateIsCompiledWhenTheGeneratorIsBuilt(): void
    {
        // Sources unique to this run: a template class another test already loaded is not written to the cache again.
        $token = bin2hex(random_bytes(6));
        $cache = sys_get_temp_dir().'/tilttek-generator-compiled-'.$token;

        try {
            new Generator(Catalogs::of(['l' => ['a']], ['g' => ['templates' => ["{{ pick('l') }}".$token, 'b'.$token], 'vars' => ['v' => 'c'.$token], 'state' => ['s' => 'd'.$token]]]), cache: $cache);
            $compiled = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($cache, FilesystemIterator::SKIP_DOTS));

            self::assertCount(4, iterator_to_array($compiled));
        } finally {
            if (is_dir($cache)) {
                foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($cache, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $file) {
                    $this->assertInstanceOf(SplFileInfo::class, $file);
                    $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
                }

                rmdir($cache);
            }
        }
    }
}
