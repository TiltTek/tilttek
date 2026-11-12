<?php

declare(strict_types=1);

namespace Tilttek\GeneratorBundle\Infrastructure\Console;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tilttek\Generator\Exception\InvalidCatalog;
use Tilttek\Generator\Exception\InvalidDefinition;
use Tilttek\GeneratorBundle\Infrastructure\Catalog\GeneratorFactory;

/**
 * Reads the lists and the generators from their files, not from the cache, and lists every problem: a document of the
 * wrong shape, a template that does not compile or leaves the sandbox, a list, a tag or a generator that does not exist.
 */
#[AsCommand('tilttek:generator:validate', 'Checks the lists and the generators of the application')]
final readonly class ValidateCommand
{
    public function __construct(private GeneratorFactory $factory)
    {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        try {
            $catalog = $this->factory->catalog()->fresh();
            $this->factory->build($catalog);
        } catch (InvalidDefinition $invalid) {
            $io->error($invalid->getMessage());

            return Command::FAILURE;
        } catch (InvalidCatalog $invalid) {
            $io->error(\sprintf('%d problems were found.', \count($invalid->problems())));
            $io->listing($invalid->problems());

            return Command::FAILURE;
        }

        $io->success(\sprintf('%d lists and %d generators are valid.', \count($catalog->lists()), \count($catalog->generators())));

        return Command::SUCCESS;
    }
}
