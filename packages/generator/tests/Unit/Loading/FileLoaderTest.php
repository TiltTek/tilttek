<?php

declare(strict_types=1);

namespace Tilttek\Generator\Tests\Unit\Loading;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\Generator\Draw;
use Tilttek\Generator\Exception\InvalidDefinition;
use Tilttek\Generator\Generator;
use Tilttek\Generator\Loading\FileLoader;

#[CoversClass(FileLoader::class)]
final class FileLoaderTest extends TestCase
{
    private const array FILES = ['nested/c.yml', 'a/x.yaml', 'a-b.yaml', 'a.JSON', 'b.yaml', 'notes.txt', 'bad.json', 'bad.yaml'];

    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/tilttek-generator-'.bin2hex(random_bytes(4));
        mkdir($this->directory.'/nested', 0o777, true);
        mkdir($this->directory.'/a');
        file_put_contents($this->directory.'/b.yaml', "lists:\n  weapon: [sword]\n");
        // The same key twice: JSON keeps the last one, where YAML would refuse the document.
        file_put_contents($this->directory.'/a.JSON', '{"generators": {"x": {"templates": "x"}}, "generators": {"item": {"templates": "{{ pick(\'weapon\') }}"}}}');
        file_put_contents($this->directory.'/nested/c.yml', "list: tool\nentries: [hammer]\n");
        file_put_contents($this->directory.'/notes.txt', 'not read');
    }

    protected function tearDown(): void
    {
        foreach (self::FILES as $file) {
            @unlink($this->directory.'/'.$file);
        }

        rmdir($this->directory.'/nested');
        rmdir($this->directory.'/a');
        rmdir($this->directory);
    }

    public function testItReadsYamlAndJsonFilesOfADirectoryInOrder(): void
    {
        $loader = new FileLoader();

        self::assertSame([$this->directory.'/a.JSON', $this->directory.'/b.yaml', $this->directory.'/nested/c.yml'], $loader->files([$this->directory]));

        $catalog = $loader->catalog([$this->directory]);
        self::assertSame(['weapon', 'tool'], array_keys($catalog->lists()));
        self::assertSame(['item'], array_keys($catalog->generators()));
    }

    public function testTheFilesAreInTheOrderOfTheirPaths(): void
    {
        file_put_contents($this->directory.'/a/x.yaml', "lists:\n  x: [x]\n");
        file_put_contents($this->directory.'/a-b.yaml', "lists:\n  y: [y]\n");

        self::assertSame(
            [$this->directory.'/a-b.yaml', $this->directory.'/a.JSON', $this->directory.'/a/x.yaml', $this->directory.'/b.yaml', $this->directory.'/nested/c.yml'],
            new FileLoader()->files([$this->directory]),
        );
    }

    public function testItReadsFilesGivenOneByOne(): void
    {
        $loader = new FileLoader();

        self::assertSame([$this->directory.'/notes.txt', $this->directory.'/b.yaml'], $loader->files([$this->directory.'/notes.txt', $this->directory.'/b.yaml']));
        self::assertSame([$this->directory.'/b.yaml' => ['lists' => ['weapon' => ['sword']]]], $loader->read([$this->directory.'/b.yaml']));
    }

    public function testAMissingPathIsAnError(): void
    {
        $this->expectExceptionObject(InvalidDefinition::at($this->directory.'/missing', 'no such file or directory'));

        new FileLoader()->files([$this->directory.'/missing']);
    }

    public function testInvalidJsonIsAnError(): void
    {
        file_put_contents($this->directory.'/bad.json', '{');
        $this->expectException(InvalidDefinition::class);
        $this->expectExceptionMessageIsOrContains($this->directory.'/bad.json: Syntax error');

        new FileLoader()->read([$this->directory.'/bad.json']);
    }

    public function testInvalidYamlIsAnError(): void
    {
        file_put_contents($this->directory.'/bad.yaml', "a: [\n");
        $this->expectException(InvalidDefinition::class);
        $this->expectExceptionMessageIsOrContains($this->directory.'/bad.yaml: ');

        new FileLoader()->read([$this->directory.'/bad.yaml']);
    }

    public function testTheExamplesOfThePackageLoad(): void
    {
        $catalog = new FileLoader()->catalog([\dirname(__DIR__, 3).'/examples']);

        $names = array_keys($catalog->generators());
        sort($names);
        self::assertSame(['dungeon', 'dungeon.room', 'hexcrawl', 'name.invented', 'name.invented.pair', 'name.syllabic', 'oracle.yes-no', 'tavern', 'tavern.dish', 'tavern.name', 'travel', 'village', 'weather'], $names);

        $generator = new Generator($catalog);

        foreach (array_keys($catalog->generators()) as $name) {
            self::assertNotSame([], $generator->draw($name, new Draw(2, seed: 'example'))->results, $name);
        }

        $weather = $generator->draw('weather', new Draw(state: ['cell' => '19']));
        self::assertContains($weather->state['cell'] ?? null, ['15', '17', '18', '19']);
    }
}
