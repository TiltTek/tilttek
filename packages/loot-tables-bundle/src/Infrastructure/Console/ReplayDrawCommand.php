<?php

declare(strict_types=1);

namespace Tilttek\LootTablesBundle\Infrastructure\Console;

use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tilttek\LootTables\Draw\LootResult;
use Tilttek\LootTables\Draw\Pick;
use Tilttek\LootTablesBundle\Application\LootResults;
use Tilttek\LootTablesBundle\Domain\Model\LootDraw;

/**
 * Rolls a revealed draw again from the seed it published, `bin/console tilttek:loot-tables:replay <draw>`: it writes
 * the commitment shown before the payment, the seed revealed, the entries the dice give again, and whether they are the
 * ones kept and the proof checks out. It succeeds only when they are.
 */
#[AsCommand(name: self::NAME, description: 'Rolls a revealed loot draw again from its seed and checks it against its commitment')]
final readonly class ReplayDrawCommand
{
    public const string NAME = 'tilttek:loot-tables:replay';

    public function __construct(
        private LootResults $results,
    ) {
    }

    public function __invoke(SymfonyStyle $io, #[Argument('The identifier of the draw, 32 hexadecimal digits')] string $draw): int
    {
        $found = $this->results->draw($draw);

        if (!$found instanceof LootDraw) {
            $io->error(\sprintf('No draw has the identifier %s.', $draw));

            return Command::FAILURE;
        }

        $replayed = $this->results->replay($found);

        if (!$replayed instanceof LootResult) {
            $io->error(\sprintf('The draw %s has no seed to roll again: it is not revealed yet, or its dice were entered at a register.', $draw));

            return Command::FAILURE;
        }

        $io->definitionList(
            ['Commitment' => $found->commitment() ?? ''],
            ['Seed' => $found->revealedSeed() ?? ''],
            ['Client seed' => $found->clientSeedOfReveal()],
        );
        $io->listing(array_map(static fn (Pick $pick): string => \sprintf('%s: %s (%d)', $pick->table->name, $pick->entry->name, $pick->face), $replayed->picks));

        $kept = $this->results->result($found);

        if ($kept instanceof LootResult && $replayed->faces() === $kept->faces() && $this->results->verify($found)) {
            $io->success('The dice rolled again from the seed are the ones kept, and the seed is the one committed to.');

            return Command::SUCCESS;
        }

        $io->error('The draw does not check out: the dice rolled again or the seed are not the ones committed to.');

        return Command::FAILURE;
    }
}
