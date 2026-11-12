<?php

declare(strict_types=1);

namespace Tilttek\GeneratorBundle\Tests\Unit\Infrastructure;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Tilttek\Generator\Grammar\Grammars;
use Tilttek\Generator\Limits;
use Tilttek\GeneratorBundle\Application\Contract\StateStore;
use Tilttek\GeneratorBundle\Application\Exception\InvalidStateKey;
use Tilttek\GeneratorBundle\Application\MemoryTables;
use Tilttek\GeneratorBundle\Infrastructure\Catalog\CachedCatalog;
use Tilttek\GeneratorBundle\Infrastructure\Catalog\FileChanges;
use Tilttek\GeneratorBundle\Infrastructure\Catalog\GeneratorFactory;
use Tilttek\GeneratorBundle\Infrastructure\Console\CreateCommand;
use Tilttek\GeneratorBundle\Infrastructure\Console\DrawCommand;
use Tilttek\GeneratorBundle\Infrastructure\Console\ValidateCommand;
use Tilttek\GeneratorBundle\Infrastructure\State\InMemoryStateStore;
use Tilttek\GeneratorBundle\Tests\Fixture\Files;

#[CoversClass(ValidateCommand::class)]
#[CoversClass(CreateCommand::class)]
#[CoversClass(DrawCommand::class)]
#[CoversClass(MemoryTables::class)]
#[CoversClass(InvalidStateKey::class)]
#[CoversClass(FileChanges::class)]
final class CommandsTest extends TestCase
{
    private string $directory;

    #[Override]
    protected function setUp(): void
    {
        $this->directory = Files::directory();
    }

    #[Override]
    protected function tearDown(): void
    {
        Files::remove($this->directory);
    }

    public function testValidCatalogsAreSaidSo(): void
    {
        $tester = $this->tester('tilttek:generator:validate');

        self::assertSame(0, $tester->execute([]));
        self::assertStringContainsString($this->compact('1 lists and 3 generators are valid.'), $this->display($tester));
    }

    public function testEveryProblemIsListed(): void
    {
        file_put_contents($this->directory.'/generators/bad.yaml', "generators:\n  bad:\n    templates: \"{{ pick('nope') }}{{ generate('none') }}\"\n");
        $tester = $this->tester('tilttek:generator:validate');

        self::assertSame(1, $tester->execute([]));
        $display = $this->display($tester);
        self::assertStringContainsString($this->compact('2 problems were found.'), $display);
        self::assertStringContainsString($this->compact('bad:template:0: the list "nope" does not exist'), $display);
        self::assertStringContainsString($this->compact('bad:template:0: the generator "none" does not exist'), $display);
    }

    public function testADocumentOfTheWrongShapeIsSaid(): void
    {
        file_put_contents($this->directory.'/generators/bad.yaml', "lists: [x]\n");
        $tester = $this->tester('tilttek:generator:validate');

        self::assertSame(1, $tester->execute([]));
        self::assertStringContainsString($this->compact('a map of names is expected'), $this->display($tester));
    }

    public function testANewGeneratorIsWrittenAndValid(): void
    {
        $tester = $this->tester('tilttek:generator:create');

        self::assertSame(0, $tester->execute(['name' => 'tavern.sign', '--kind' => 'fields', '--locale' => 'fr']));
        self::assertStringContainsString($this->compact('tavern.sign.yaml'), $this->display($tester));
        self::assertSame(
            "lists:\n  tavern.sign.word:\n    - first\n    - second\n    - third\ngenerators:\n  tavern.sign:\n    locale: fr\n    fields:\n      name: \"{{ pick('tavern.sign.word')|capitalize }}\"\n      description: \"{{ name }} and {{ pick('tavern.sign.word') }}\"\n",
            file_get_contents($this->directory.'/generators/tavern.sign.yaml'),
        );
        self::assertSame(0, $tester->execute(['name' => 'plain']));
        self::assertSame(0, $tester->execute(['name' => 'village', '--kind' => 'map']));
        self::assertStringNotContainsString('locale', (string) file_get_contents($this->directory.'/generators/plain.yaml'));
        self::assertStringContainsString("    templates:\n      - \"{{ pick('plain.word')|capitalize }}\"\n", (string) file_get_contents($this->directory.'/generators/plain.yaml'));
        self::assertStringContainsString("    map:\n      layout: square\n      width: 4\n      height: 4\n      cell: \"{{ pick('village.word') }}\"\n", (string) file_get_contents($this->directory.'/generators/village.yaml'));

        $validate = $this->tester('tilttek:generator:validate');
        self::assertSame(0, $validate->execute([]));
        self::assertStringContainsString($this->compact('4 lists and 6 generators are valid.'), $this->display($validate));
    }

    public function testANewTableWithAMemoryIsWrittenAndDraws(): void
    {
        $tester = $this->tester('tilttek:generator:create');

        self::assertSame(0, $tester->execute(['name' => 'days', '--kind' => 'memory']));
        self::assertSame(
            "lists:\n  days.word:\n    - first\n    - second\n    - third\ngenerators:\n  days:\n    state:\n      count:\n        start: \"1\"\n        next: \"{{ state.count + 1 }}\"\n    templates:\n      - \"{{ state.count }}: {{ pick('days.word') }}\"\n",
            file_get_contents($this->directory.'/generators/days.yaml'),
        );

        $draw = $this->tester('tilttek:generator:draw');
        self::assertSame(0, $draw->execute(['name' => 'days', '--count' => '2']));
        self::assertMatchesRegularExpression('/^1: \w+\n2: \w+\n/', $draw->getDisplay());
        self::assertStringContainsString($this->compact('// State: {"count":"2"}'), $this->display($draw));
    }

    public function testATableOfTheLibraryIsCopiedUnderANewName(): void
    {
        $tester = $this->tester('tilttek:generator:create');

        self::assertSame(0, $tester->execute(['name' => 'tavern.shop', '--from' => 'shop', '--kind' => 'map']));
        $copy = (string) file_get_contents($this->directory.'/generators/tavern.shop.yaml');
        self::assertStringNotContainsString('table.shop', $copy);
        self::assertStringContainsString("  tavern.shop:\n    vars:", $copy);
        self::assertStringContainsString("pick('tavern.shop.item', 5, where: {type: type})", $copy);

        $draw = $this->tester('tilttek:generator:draw');
        self::assertSame(0, $draw->execute(['name' => 'tavern.shop', '--seed' => 'x']));
        self::assertStringContainsString($this->compact('"merchant": {'), $this->display($draw));

        self::assertSame(0, $tester->execute(['name' => 'auberge.shop', '--from' => 'shop', '--locale' => 'fr']));
        $french = (string) file_get_contents($this->directory.'/generators/auberge.shop.yaml');
        self::assertStringContainsString("  auberge.shop:\n    locale: fr\n", $french);
        self::assertStringContainsString('{ text: forge, tags: { type: [smithy] } }', $french);
        self::assertSame(1, $tester->execute(['name' => 'y', '--from' => 'shop', '--locale' => 'de']));
        self::assertStringContainsString($this->compact('The tables are not written in "de": en, fr.'), $this->display($tester));
        self::assertFileDoesNotExist($this->directory.'/generators/y.yaml');
    }

    public function testATableWithAMemoryGoesOnFromTheStateOfItsKey(): void
    {
        file_put_contents($this->directory.'/generators/days.yaml', "generators:\n  days:\n    state:\n      day: { start: '1', next: '{{ state.day + 1 }}' }\n    templates: 'Day {{ state.day }}'\n");
        $store = new InMemoryStateStore();
        $tester = $this->tester('tilttek:generator:draw', $store);

        self::assertSame(0, $tester->execute(['name' => 'days', '--count' => '2', '--key' => 'campaign']));
        self::assertMatchesRegularExpression('/^Day 1\nDay 2\n/', $tester->getDisplay());
        self::assertSame(['day' => '2'], $store->load('days', 'campaign'));

        self::assertSame(0, $tester->execute(['name' => 'days', '--key' => 'campaign']));
        self::assertMatchesRegularExpression('/^Day 3\n/', $tester->getDisplay());
        self::assertSame(0, $tester->execute(['name' => 'days', '--key' => 'other']));
        self::assertMatchesRegularExpression('/^Day 1\n/', $tester->getDisplay());
        self::assertSame(0, $tester->execute(['name' => 'days', '--key' => 'campaign', '--reset' => true]));
        self::assertMatchesRegularExpression('/^Day 1\n/', $tester->getDisplay());
        self::assertSame(['day' => '1'], $store->load('days', 'campaign'));

        self::assertSame(0, $tester->execute(['name' => 'days']));
        self::assertSame(['day' => '1'], $store->load('days', 'campaign'));
        self::assertSame(0, $tester->execute(['name' => 'name.short', '--key' => 'campaign']));
        self::assertStringNotContainsString('State', $tester->getDisplay());
        self::assertNull($store->load('name.short', 'campaign'));

        self::assertSame(1, $tester->execute(['name' => 'days', '--key' => '']));
        self::assertStringContainsString($this->compact('The key "" of a state is a text of 1 to 200 characters.'), $this->display($tester));
        self::assertSame(1, $tester->execute(['name' => 'days', '--key' => str_repeat('k', 201), '--reset' => true]));
        self::assertStringContainsString($this->compact('of a state is a text of 1 to 200 characters.'), $this->display($tester));
    }

    public function testTheStateIsWrittenWithItsLetters(): void
    {
        file_put_contents($this->directory.'/generators/season.yaml', "generators:\n  season:\n    state:\n      name: été\n    templates: '{{ state.name }}'\n");
        $tester = $this->tester('tilttek:generator:draw', new InMemoryStateStore());

        self::assertSame(0, $tester->execute(['name' => 'season', '--key' => 'campaign']));
        self::assertStringContainsString($this->compact('// State: {"name":"été"}'), $this->display($tester));
    }

    public function testAKeyNeedsAStore(): void
    {
        $tester = $this->tester('tilttek:generator:draw');

        self::assertSame(1, $tester->execute(['name' => 'name.short', '--key' => 'campaign']));
        self::assertStringContainsString($this->compact('No store keeps the states: install a cache pool, or name a store under tilttek_generator.state.store.'), $this->display($tester));
        self::assertSame(0, $tester->execute(['name' => 'name.short']));
    }

    public function testANewGeneratorIsRefusedWhenItCannotBeWritten(): void
    {
        $tester = $this->tester('tilttek:generator:create');

        self::assertSame(1, $tester->execute(['name' => 'names']));
        self::assertSame(1, $tester->execute(['name' => 'names']));
        self::assertStringContainsString($this->compact('names.yaml" exists already.'), $this->display($tester));
        self::assertSame(1, $tester->execute(['name' => 'a b']));
        self::assertStringContainsString($this->compact('"a b" is not a name'), $this->display($tester));
        self::assertSame(1, $tester->execute(['name' => 'x', '--kind' => 'table']));
        self::assertStringContainsString($this->compact('"table" is not a kind: text, fields, map or memory.'), $this->display($tester));
        self::assertSame(1, $tester->execute(['name' => 'x', '--from' => 'tavern']));
        self::assertStringContainsString($this->compact('"tavern" is not a table: encounter, fortune, hook, loot, magic-item, oracle, rumour, shop.'), $this->display($tester));
        self::assertFileDoesNotExist($this->directory.'/generators/x.yaml');

        $none = new CommandTester($this->application([$this->directory.'/generators/names.yaml'])->find('tilttek:generator:create'));
        self::assertSame(1, $none->execute(['name' => 'x']));
        self::assertStringContainsString($this->compact('No directory of generators exists'), $this->display($none));
    }

    public function testADrawIsWrittenInTheTerminal(): void
    {
        $tester = $this->tester('tilttek:generator:draw');

        self::assertSame(0, $tester->execute(['name' => 'name.short', '--count' => '3', '--unique' => true, '--seed' => 'x']));
        self::assertMatchesRegularExpression('/^([A-Z][a-z]{3}\n){3}$/', $tester->getDisplay());

        self::assertSame(0, $tester->execute(['name' => 'inn']));
        self::assertStringContainsString($this->compact('"staff": ['), $this->display($tester));
        self::assertStringContainsString($this->compact('"sign": "Café"'), $this->display($tester));

        self::assertSame(0, $tester->execute(['name' => 'name.short']));
        self::assertMatchesRegularExpression('/^[A-Z][a-z]{3}\n$/', $tester->getDisplay());

        self::assertSame(0, $tester->execute(['name' => 'name.short', '--count' => '9']));
        self::assertMatchesRegularExpression('/^([A-Z][a-z]{3}\n){9}$/', $tester->getDisplay());

        self::assertSame(0, $tester->execute(['name' => 'name.short', '--count' => '9', '--unique' => true]));
        self::assertStringContainsString($this->compact('Only 6 different results of 9 were found.'), $this->display($tester));

        self::assertSame(1, $tester->execute(['name' => 'nope']));
        self::assertStringContainsString($this->compact('No generator is named "nope".'), $this->display($tester));

        file_put_contents($this->directory.'/generators/bad.yaml', "lists: [x]\n");
        self::assertSame(1, $tester->execute(['name' => 'name.short']));
        self::assertStringContainsString($this->compact('a map of names is expected'), $this->display($tester));
    }

    /**
     * What a command said, without its spaces and line breaks: SymfonyStyle wraps its blocks at the width of the
     * terminal, and may cut a long temporary path in two.
     */
    private function display(CommandTester $tester): string
    {
        return $this->compact($tester->getDisplay());
    }

    private function compact(string $text): string
    {
        return preg_replace('/\s+/', '', $text) ?? $text;
    }

    private function tester(string $command, ?StateStore $store = null): CommandTester
    {
        return new CommandTester($this->application([$this->directory.'/generators'], $store)->find($command));
    }

    /**
     * @param list<string> $paths
     */
    private function application(array $paths, ?StateStore $store = null): Application
    {
        $catalog = new CachedCatalog($paths, $this->directory.'/cache/catalog.php', true);
        $factory = new GeneratorFactory($catalog, new Limits(), 'en', new Grammars());
        $application = new Application();
        $application->addCommand(new ValidateCommand($factory));
        $application->addCommand(new CreateCommand($catalog));
        $application->addCommand(new DrawCommand($factory, $store));

        return $application;
    }
}
