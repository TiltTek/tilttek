<?php

declare(strict_types=1);

namespace Tilttek\GeneratorBundle\Infrastructure\Console;

use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tilttek\Generator\Draw;
use Tilttek\Generator\DrawResult;
use Tilttek\Generator\Exception\GeneratorException;
use Tilttek\GeneratorBundle\Application\Contract\StateStore;
use Tilttek\GeneratorBundle\Application\Exception\InvalidStateKey;
use Tilttek\GeneratorBundle\Application\MemoryTables;
use Tilttek\GeneratorBundle\Infrastructure\Catalog\GeneratorFactory;

/**
 * Draws from a generator in the terminal: a text by line, a structured result or a map in JSON. A table with a memory
 * goes on from the state kept under `--key` and keeps the one it leaves, which `--reset` forgets first; the state is
 * written after the results. A catalog that does not load is said, as `tilttek:generator:validate` says it.
 */
#[AsCommand('tilttek:generator:draw', 'Draws results from a generator')]
final readonly class DrawCommand
{
    public function __construct(private GeneratorFactory $factory, private ?StateStore $store = null)
    {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument('The name of the generator')]
        string $name,
        #[Option('How many results')]
        int $count = 1,
        #[Option('Different results only')]
        bool $unique = false,
        #[Option('A seed that makes the same draw come again')]
        ?string $seed = null,
        #[Option('The key under which a table with a memory keeps its state, such as a campaign')]
        ?string $key = null,
        #[Option('Forgets the state kept under the key before the draw')]
        bool $reset = false,
    ): int {
        try {
            if (null === $key) {
                $result = $this->factory->generator()->draw($name, new Draw($count, $unique, $seed));
            } elseif ($this->store instanceof StateStore) {
                $result = $this->remember(new MemoryTables($this->factory->generator(), $this->store), $name, $key, $reset, new Draw($count, $unique, $seed));
            } else {
                $io->error('No store keeps the states: install a cache pool, or name a store under tilttek_generator.state.store.');

                return Command::FAILURE;
            }
        } catch (GeneratorException|InvalidStateKey $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        foreach ($result->results as $value) {
            $io->writeln(\is_string($value) ? $value : json_encode($value, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR));
        }

        if (null !== $result->state) {
            $io->comment('State: '.json_encode($result->state, \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR));
        }

        if ($result->isShort()) {
            $io->warning(\sprintf('Only %d different results of %d were found.', \count($result->results), $result->requested));
        }

        return Command::SUCCESS;
    }

    /**
     * Draws from the state kept under the key, forgotten first when asked.
     */
    private function remember(MemoryTables $tables, string $name, string $key, bool $reset, Draw $draw): DrawResult
    {
        if ($reset) {
            $tables->forget($name, $key);
        }

        return $tables->draw($name, $key, $draw->count, $draw->unique, $draw->seed);
    }
}
