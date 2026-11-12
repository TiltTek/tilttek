<?php

declare(strict_types=1);

namespace Tilttek\GeneratorBundle\Infrastructure\Console;

use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tilttek\Generator\Loading\Reader;
use Tilttek\Generator\Tables;
use Tilttek\GeneratorBundle\Infrastructure\Catalog\CachedCatalog;

/**
 * Writes a new generator and its first list in a YAML file of the first configured directory: a text, a structured
 * result (`--kind=fields`), a map (`--kind=map`) or a table with a memory (`--kind=memory`), in a language
 * (`--locale=fr`); or a copy of one of the library's random tables (`--from=shop`), in English or in French
 * (`--locale=fr`), its names `table.shop…` renamed after the new generator, to adapt.
 */
#[AsCommand('tilttek:generator:create', 'Writes a new generator in the first directory of the generators')]
final readonly class CreateCommand
{
    private const array BODIES = [
        'text' => "    templates:\n      - \"{{ pick('%1\$s.word')|capitalize }}\"\n",
        'fields' => "    fields:\n      name: \"{{ pick('%1\$s.word')|capitalize }}\"\n      description: \"{{ name }} and {{ pick('%1\$s.word') }}\"\n",
        'map' => "    map:\n      layout: square\n      width: 4\n      height: 4\n      cell: \"{{ pick('%1\$s.word') }}\"\n",
        'memory' => "    state:\n      count:\n        start: \"1\"\n        next: \"{{ state.count + 1 }}\"\n    templates:\n      - \"{{ state.count }}: {{ pick('%1\$s.word') }}\"\n",
    ];

    public function __construct(private CachedCatalog $catalog)
    {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument('The name of the generator, such as "name.elf"')]
        string $name,
        #[Option('text, fields, map or memory')]
        string $kind = 'text',
        #[Option('The language of the generator, such as "fr"')]
        ?string $locale = null,
        #[Option('A random table of the library to copy, such as "shop"')]
        ?string $from = null,
    ): int {
        $directory = $this->catalog->directory();
        $file = $directory.'/'.$name.'.yaml';

        $problem = match (true) {
            !Reader::isName($name) => \sprintf('"%s" is not a name: letters, digits, "_" and "-", in parts joined by dots.', $name),
            !isset(self::BODIES[$kind]) => \sprintf('"%s" is not a kind: text, fields, map or memory.', $kind),
            null !== $from && !\in_array($from, Tables::NAMES, true) => \sprintf('"%s" is not a table: %s.', $from, implode(', ', Tables::NAMES)),
            null !== $from && !\in_array($locale ?? 'en', Tables::LOCALES, true) => \sprintf('The tables are not written in "%s": %s.', $locale, implode(', ', Tables::LOCALES)),
            null === $directory => 'No directory of generators exists; create one and name it under tilttek_generator.paths.',
            file_exists($file) => \sprintf('"%s" exists already.', $file),
            default => null,
        };

        if (null !== $problem) {
            $io->error($problem);

            return Command::FAILURE;
        }

        file_put_contents($file, null === $from ? \sprintf(
            "lists:\n  %1\$s.word:\n    - first\n    - second\n    - third\ngenerators:\n  %1\$s:\n%2\$s".self::BODIES[$kind],
            $name,
            null === $locale ? '' : '    locale: '.$locale."\n",
        ) : str_replace('table.'.$from, $name, (string) file_get_contents(Tables::file($from, $locale ?? 'en'))));
        $io->success(\sprintf('The generator "%s" is written in "%s".', $name, $file));

        return Command::SUCCESS;
    }
}
